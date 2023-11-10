<?php

declare(strict_types=1);

namespace Drupal\webauthn\Form;

use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\user\UserInterface;
use Drupal\user\UserStorageInterface;
use Drupal\webauthn\ServerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implements login form based on PK validation.
 */
class PublicKeyCredentialRequestForm extends FormBase {

  public const ASSERTION_PREPARE = 'prepare';

  public const ASSERTION_HANDLE = 'handle';

  /**
   * The flood service.
   *
   * @var \Drupal\Core\Flood\FloodInterface
   */
  protected $flood;

  /**
   * The user storage.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected $userStorage;

  /**
   * The WebAuthn server instance.
   *
   * @var \Drupal\webauthn\ServerInterface
   */
  protected $server;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The form step.
   *
   * @var string
   */
  protected $step;

  /**
   * {@inheritdoc}
   */
  public function __construct(FloodInterface $flood, UserStorageInterface $user_storage, ServerInterface $server, RendererInterface $renderer) {
    $this->flood = $flood;
    $this->userStorage = $user_storage;
    $this->server = $server;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('flood'),
      $container->get('entity_type.manager')->getStorage('user'),
      $container->get('webauthn.server'),
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'pk_credential_request_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('system.site');
    $this->step = $form_state->get('step') ?? self::ASSERTION_PREPARE;
    $form['#attached']['library'][] = 'core/drupal.form';
    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
      '#size' => 60,
      '#maxlength' => UserInterface::USERNAME_MAX_LENGTH,
      '#description' => $this->t('Enter your @s username.', ['@s' => $config->get('name')]),
      '#required' => TRUE,
      '#attributes' => [
        'autocorrect' => 'none',
        'autocapitalize' => 'none',
        'spellcheck' => 'false',
        'autofocus' => 'autofocus',
      ],
    ];

    $form['response'] = [
      '#type' => 'hidden',
      '#value' => $form_state->getValue('response'),
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#attributes' => [
        'data-trigger' => 'webauthn',
      ],
      '#value' => $this->t('Log in', [], ['context' => 'WebAuthn']),
    ];

    if ($this->step === self::ASSERTION_PREPARE) {
      $form['#validate'][] = '::validateName';
    }

    if ($this->step === self::ASSERTION_HANDLE) {
      $form['name']['#type'] = 'hidden';
      $form['name_value'] = [
        '#type' => 'item',
        '#markup' => $this->t('Trying to log in as %name', [
          '%name' => $form_state->getValue('name'),
        ], ['context' => 'WebAuthn']),
      ];
      $form['#validate'][] = '::validateAuthentication';
      $form['#validate'][] = '::validateFinal';
      $form['actions']['submit']['#value'] = $this->t('Assert authentication', [], ['context' => 'WebAuthn']);
      $form['#attached']['library'][] = 'webauthn/assertion';
      $form['#attached']['drupalSettings']['webauthn'] = [
        'assertion' => $form_state->get('assertion_options'),
      ];
    }

    // $this->renderer->addCacheableDependency($form, $config);
    return $form;
  }

  /**
   * Sets an error if supplied username has been blocked.
   *
   * @param array $form
   *   The form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form-state instance.
   */
  public function validateName(array &$form, FormStateInterface $form_state): void {
    if (!$form_state->isValueEmpty('name')) {
      $account = $this->userStorage->loadByProperties(['name' => $form_state->getValue('name')]);
      $account = reset($account);
      if (!$account) {
        // Use $form_state->getUserInput() in the error message to guarantee
        // that we send exactly what the user typed in. The value from
        // $form_state->getValue() may have been modified by validation
        // handlers that ran earlier than this one.
        $user_input = $form_state->getUserInput();
        $message = $this->t('Unrecognized username %name.', [
          '%name' => $user_input['name'],
        ], ['context' => 'WebAuthn']);
        $form_state->setErrorByName('name',
          $message);

        return;
      }

      if (user_is_blocked($form_state->getValue('name'))) {
        $message = $this->t('The username %name has not been activated or is blocked.', [
          '%name' => $form_state->getValue('name'),
        ], ['context' => 'WebAuthn']);
        $form_state->setErrorByName('name', $message);

        return;
      }

      $form_state->set('user', $account);
    }
  }

  /**
   * Checks supplied username/password against local users table.
   *
   * If successful, $form_state->get('uid') is set to the matching user ID.
   *
   * @param array $form
   *   The form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form-state instance.
   */
  public function validateAuthentication(array &$form, FormStateInterface $form_state): void {
    if ($form_state->isValueEmpty('response')) {
      $form_state->setErrorByName('name', $this->t('The credential verification process failed, either they are invalid or the process has timeout.', [], ['context' => 'WebAuthn']));

      return;
    }

    $flood_config = $this->config('user.flood');
    $response = $form_state->getValue('response');

    // Do not allow any login from the current user's IP if the limit has been
    // reached. Default is 50 failed attempts allowed in one hour. This is
    // independent of the per-user limit to catch attempts from one IP to log
    // in to many different user accounts.  We have a reasonably high limit
    // since there may be only one apparent IP for all users at an institution.
    if (!$this->flood->isAllowed('user.failed_login_ip', $flood_config->get('ip_limit'), $flood_config->get('ip_window'))) {
      $form_state->set('flood_control_triggered', 'ip');

      return;
    }
    $account = $form_state->get('user');

    if ($account) {
      if ($flood_config->get('uid_only')) {
        // Register flood events based on the uid only, so they apply for any
        // IP address. This is the most secure option.
        $identifier = $account->id();
      }
      else {
        // The default identifier is a combination of uid and IP address. This
        // is less secure but more resistant to denial-of-service attacks that
        // could lock out all users with public user names.
        $identifier = $account->id() . '-' . $this->getRequest()->getClientIP();
      }
      $form_state->set('flood_control_user_identifier', $identifier);

      // Don't allow login if the limit for this user has been reached.
      // Default is to allow 5 failed attempts every 6 hours.
      if (!$this->flood->isAllowed('user.failed_login_user', $flood_config->get('user_limit'), $flood_config->get('user_window'), $identifier)) {
        $form_state->set('flood_control_triggered', 'user');

        return;
      }
    }
    // We are not limited by flood control, so try to authenticate.
    // Store $uid in form state as a flag for self::validateFinal().
    $source = $this->server->handleAssertion($account, $response);
    if ($source === NULL) {
      $form_state->setErrorByName('name', $this->t('The credential verification process failed, login attempt canceled.', [], ['context' => 'WebAuthn']));
      $form_state->set('step', self::ASSERTION_PREPARE);
      $form_state->setRebuild();
    }

    $form_state->set('credential_source', $source);
  }

  /**
   * Checks if user was not authenticated, or if too many logins were attempted.
   *
   * This validation function should always be the last one.
   *
   * @param array $form
   *   The form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form-state instance.
   */
  public function validateFinal(array &$form, FormStateInterface $form_state): void {
    $flood_config = $this->config('user.flood');
    if (!$form_state->get('credential_source')) {
      // Always register an IP-based failed login event.
      $this->flood->register('user.failed_login_ip', $flood_config->get('ip_window'));
      // Register a per-user failed login event.
      if ($flood_control_user_identifier = $form_state->get('flood_control_user_identifier')) {
        $this->flood->register('user.failed_login_user', $flood_config->get('user_window'), $flood_control_user_identifier);
      }

      if ($flood_control_triggered = $form_state->get('flood_control_triggered')) {
        if ($flood_control_triggered == 'user') {
          $form_state->setErrorByName('name', $this->formatPlural($flood_config->get('user_limit'), 'There has been more than one failed login attempt for this account. It is temporarily blocked. Try again later.', 'There have been more than @count failed login attempts for this account. It is temporarily blocked. Try again later.'));
        }
        else {
          // We did not find a uid, so the limit is IP-based.
          $form_state->setErrorByName('name', $this->t('Too many failed login attempts from your IP address. This IP address is temporarily blocked. Try again later.'));
        }
      }
      else {
        $this->logger('webauthn')
          ->notice('Login attempt failed for %user from %ip.', [
            '%user' => $form_state->getValue('name'),
            '%ip' => $this->getRequest()->getClientIp(),
          ]);
      }
    }
    elseif ($flood_control_user_identifier = $form_state->get('flood_control_user_identifier')) {
      // Clear past failures for this user so as not to block a user who might
      // log in and out more than once in an hour.
      $this->flood->clear('user.failed_login_user', $flood_control_user_identifier);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($this->step === self::ASSERTION_PREPARE) {
      /** @var \Drupal\user\UserInterface $user */
      $user = $form_state->get('user');
      $options = $this->server->assertion($user);
      $form_state->set('assertion_options', $options);
      $form_state->set('step', self::ASSERTION_HANDLE);
      $form_state->setRebuild();
    }

    if ($this->step === self::ASSERTION_HANDLE) {
      $account = $form_state->get('user');
      // A destination was set, probably on an exception controller,.
      if (!$this->getRequest()->request->has('destination')) {
        $form_state->setRedirect(
          'entity.user.canonical',
          ['user' => $account->id()]
        );
      }
      else {
        $this->getRequest()->query->set('destination', $this->getRequest()->request->get('destination'));
      }

      user_login_finalize($account);
    }
  }

}
