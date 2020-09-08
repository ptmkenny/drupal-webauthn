<?php

declare(strict_types=1);

namespace Drupal\webauthn\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityConstraintViolationListInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\webauthn\ServerInterface;
use Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implements a registration form based on PK creation.
 */
class PublicKeyCredentialCreationForm extends AccountForm {

  public const ATTESTATION_PREPARE = 'prepare';

  public const ATTESTATION_HANDLE = 'handle';

  /**
   * The WebAuthn server instance.
   *
   * @var \Drupal\webauthn\ServerInterface
   */
  protected $server;

  /**
   * The form step.
   *
   * @var string
   */
  protected $step;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    ServerInterface $server,
    EntityRepositoryInterface $entity_repository,
    LanguageManagerInterface $language_manager,
    EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL,
    TimeInterface $time = NULL) {
    parent::__construct($entity_repository, $language_manager, $entity_type_bundle_info, $time);
    $this->server = $server;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('webauthn.server'),
      $container->get('entity.repository'),
      $container->get('language_manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $form['#attached']['library'][] = 'core/drupal.form';
    $form['#limit_validation_errors'] = [];
    unset($form['user_picture']);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $this->step = $form_state->get('step') ?? self::ATTESTATION_PREPARE;
    $form = parent::form($form, $form_state);
    $form['response'] = [
      '#type' => 'hidden',
      '#value' => $form_state->getValue('response'),
    ];

    if ($this->step === self::ATTESTATION_HANDLE) {
      $form['account']['#access'] = FALSE;
      $form['#attached']['library'][] = 'webauthn/attestation';
      $form['#attached']['drupalSettings']['webauthn'] = [
        'attestation' => $form_state->get('attestation_options'),
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $element = parent::actions($form, $form_state);
    $element['submit']['#attributes']['data-trigger'] = 'webauthn';
    if ($this->step === self::ATTESTATION_PREPARE) {
      $element['submit']['#submit'] = ['::submitForm'];
      $element['submit']['#value'] = $this->t('Start registration', [], ['context' => 'WebAuthn']);
    }
    else {
      $element['submit']['#submit'] = ['::submitForm', '::save'];
      $element['submit']['#value'] = $this->t('Complete account registration', [], ['context' => 'WebAuthn']);
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntity(array $form, FormStateInterface $form_state) {
    $entity = $form_state->get('user');

    if ($entity === NULL) {
      $entity = parent::buildEntity($form, $form_state);
      $entity->set('name', $form_state->getValue('name'));
      $entity->set('mail', $form_state->getValue('mail'));
      $entity->set('pass', user_password());
      $entity->set('init', $form_state->getValue('mail'));
    }

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\user\UserInterface|null $entity */
    $entity = parent::validateForm($form, $form_state);

    if ($entity && $this->step === self::ATTESTATION_HANDLE) {
      $response = $form_state->getUserInput()['response'];
      if (empty($response)) {
        $form_state->setErrorByName('name', $this->t('The credential verification process failed, either they are invalid or the process has timeout.'));

        return $entity;
      }

      $source = $this->server->handleAttestation($entity, $response);

      if ($source === NULL) {
        $form_state->setErrorByName('name', $this->t('The credential verification process failed, your account cannot be created.'));
        $form_state->set('step', self::ATTESTATION_PREPARE);
        $form_state->setRebuild();

        return $entity;
      }

      $form_state->set('credential_source', $source);
    }

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    parent::submitForm($form, $form_state);
    if ($this->step === self::ATTESTATION_PREPARE) {
      /** @var \Drupal\user\UserInterface $user */
      $user = $this->entity;
      $options = $this->server->attestation($user);
      $form_state->set('user', $user);
      $form_state->set('attestation_options', $options);
      $form_state->set('step', self::ATTESTATION_HANDLE);
      $form_state->setRebuild();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    if (!$this->getRequest()->isXmlHttpRequest()) {
      // The `credential_source` is stored during ::validateForm.
      /** @var \Webauthn\PublicKeyCredentialSource $source */
      $source = $form_state->get('credential_source');
      /** @var \Drupal\user\UserInterface $account */
      $account = $this->entity;
      $account->set('uuid', $source->getUserHandle());
      parent::save($form, $form_state);
      try {
        $this->server
          ->getPublicKeyCredentialSourceRepository()
          ->saveCredentialSource($source);
      }
      catch (Exception $e) {
        // Any error here will prevent the user from completing the
        // registration, if so, delete the user account and start all
        // over again.
        watchdog_exception('webauthn', $e);
        $account->delete();
        $replaced_route = $this->config('webauthn.settings')
          ->get('replace_registration_form');
        $form_state->setRedirect($replaced_route === TRUE ? 'user.register' : 'webauthn.register');
        $this->messenger()
          ->addError($this->t('An error has occurred during the public key generation and the registration process could not be completed. Please, try again and if the problem persists contact support.'));

        return SAVED_DELETED;
      }

      $form_state->set('user', $account);
      $form_state->setValue('uid', $account->id());

      $this->logger('user')
        ->notice('New user: %name %email.', [
          '%name' => $form_state->getValue('name'),
          '%email' => '<' . $form_state->getValue('mail') . '>',
          'type' => $account->toLink($this->t('Edit'), 'edit-form')->toString(),
        ]);

      if ($account->isActive() && !$this->config('user.settings')
          ->get('verify_mail')) {
        _user_mail_notify('register_no_approval_required', $account);
        user_login_finalize($account);
        $form_state->setRedirect('<front>');
        $this->messenger()
          ->addStatus($this->t('Registration successful. You are now logged in.'));
      }
      // No administrator approval required.
      elseif ($account->isActive()) {
        if (!$account->getEmail()) {
          $this->logger('webauthn')
            ->info($this->t('The new user <a href=":url">%name</a> was created without an email address, so no welcome message was sent.', [
              ':url' => $account->toUrl()
                ->toString(),
              '%name' => $account->getAccountName(),
            ]));
        }
        elseif (_user_mail_notify('register_no_approval_required', $account)) {
          $form_state->setRedirect('<front>');
          $this->messenger()
            ->addStatus($this->t('A welcome message with further instructions has been sent to your email address.'));
        }
      }
      // Administrator approval required.
      else {
        _user_mail_notify('register_pending_approval', $account);
        $form_state->setRedirect('<front>');
        $this->messenger()
          ->addStatus($this->t('Thank you for applying for an account. Your account is currently pending approval by the site administrator.<br />In the meantime, a welcome message with further instructions has been sent to your email address.'));
      }
    }

    return SAVED_NEW;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditedFieldNames(FormStateInterface $form_state) {
    return array_merge([
      'name',
      'mail',
    ], parent::getEditedFieldNames($form_state));
  }

  /**
   * {@inheritdoc}
   */
  protected function flagViolations(EntityConstraintViolationListInterface $violations, array $form, FormStateInterface $form_state) {
    // Manually flag violations of fields not handled by the form display. This
    // is necessary as entity form displays only flag violations for fields
    // contained in the display.
    $field_names = [
      'name',
      'mail',
    ];
    foreach ($violations->getByFields($field_names) as $violation) {
      [$field_name] = explode('.', $violation->getPropertyPath(), 2);
      $form_state->setErrorByName($field_name, $violation->getMessage());
    }
    parent::flagViolations($violations, $form, $form_state);
  }

}
