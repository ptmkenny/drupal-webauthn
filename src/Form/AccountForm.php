<?php

declare(strict_types=1);


namespace Drupal\webauthn\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityConstraintViolationListInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\language\ConfigurableLanguageManagerInterface;
use Drupal\user\Plugin\LanguageNegotiation\LanguageNegotiationUser;
use Drupal\user\Plugin\LanguageNegotiation\LanguageNegotiationUserAdmin;
use Drupal\user\RoleInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for the user account forms.
 *
 * This class is a clone of \Drupal\user\AccountForm, didn't use inheritance
 * since will end only in overriding most of the code only to strip all
 * password-related features from user account forms.
 *
 * @see \Drupal\user\AccountForm
 */
abstract class AccountForm extends ContentEntityForm implements TrustedCallbackInterface {

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * Constructs a new AccountForm object.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface|null $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface|null $time
   *   The time service.
   */
  public function __construct(EntityRepositoryInterface $entity_repository, LanguageManagerInterface $language_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL, TimeInterface $time = NULL) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): AccountForm {
    return new static(
      $container->get('entity.repository'),
      $container->get('language_manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['alterPreferredLangcodeDescription'];
  }

  /**
   * {@inheritdoc}
   *
   * @uses \Drupal\webauthn\Form\AccountForm::alterPreferredLangcodeDescription
   * @uses \Drupal\webauthn\Form\AccountForm::syncUserLangcode
   */
  public function form(array $form, FormStateInterface $form_state): array {
    /** @var \Drupal\user\UserInterface $account */
    $account = $this->entity;
    $user = $this->currentUser();
    $config = \Drupal::config('user.settings');
    $form['#cache']['tags'] = $config->getCacheTags();

    $language_interface = $this->languageManager->getCurrentLanguage();

    // Check for new account.
    $register = $account->isAnonymous();

    // For a new account, there are 2 sub-cases:
    // $self_register: A user creates their own, new, account
    // (path '/user/register')
    // $admin_create: An administrator creates a new account for another user
    // (path '/admin/people/create')
    // If the current user is logged in and has permission to create users
    // then it must be the second case.
    $admin_create = $register && $account->access('create');
    $self_register = $register && !$admin_create;

    // Account information.
    $form['account'] = [
      '#type' => 'container',
      '#weight' => -10,
    ];

    // Currently there's no API method to change the user name (or mail
    // if was entered during registration). Changing these fields alters
    // the original credential options which may result on the user locked
    // out of the system if only one authenticator is registered.
    // To prevent such DO NOT allow changing user name or mail.
    // See https://github.com/w3c/webauthn/issues/1200
    // @todo Provide a way create new credentials with different names/email.
    if (!$register) {
      $form['account']['details'] = [
        '#type' => 'item',
        '#title' => $this->t('Account details'),
        '#markup' => $this->t('Name: :name, Email: :email', [
          ':name' => $account->getAccountName(),
          ':email' => $account->getEmail() ?? (string) $this->t('Not set'),
        ], ['context' => 'WebAuthn']),
      ];
    }
    else {
      $form['account']['name'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Username'),
        '#maxlength' => UserInterface::USERNAME_MAX_LENGTH,
        '#description' => $this->t("Several special characters are allowed, including space, period (.), hyphen (-), apostrophe ('), underscore (_), and the @ sign.", [], ['context' => 'WebAuthn']),
        '#required' => TRUE,
        '#attributes' => [
          'class' => ['username'],
          'autocorrect' => 'off',
          'autocapitalize' => 'off',
          'spellcheck' => 'false',
        ],
        '#access' => $account->name->access('edit'),
      ];

      // Only show name field on registration form
      // or user can change own username.
      // The mail field is NOT required under WebAuthn perspective.
      // This allows users without email address to be edited and deleted.
      // Also see \Drupal\user\Plugin\Validation\Constraint\UserMailRequired.
      $form['account']['mail'] = [
        '#type' => 'email',
        '#title' => $this->t('Email address'),
        '#description' => $this->t('(optional) A valid email address. All emails from the system will be sent to this address. The email address is not made public.', [], ['context' => 'WebAuthn']),
        '#required' => FALSE,
        '#default_value' => '',
      ];
    }

    // When not building the user registration form, prevent web browsers from
    // autofilling/prefilling the email, username, and password fields.
    if (!$register) {
      foreach (['mail', 'name'] as $key) {
        if (isset($form['account'][$key])) {
          $form['account'][$key]['#attributes']['autocomplete'] = 'off';
        }
      }
    }

    if (!$self_register) {
      $status = $account->get('status')->value;
    }
    else {
      $status = (int) ($config->get('register') === UserInterface::REGISTER_VISITORS);
    }

    $form['account']['status'] = [
      '#type' => 'radios',
      '#title' => $this->t('Status'),
      '#default_value' => $status,
      '#options' => [$this->t('Blocked'), $this->t('Active')],
      '#access' => $account->status->access('edit'),
    ];

    $roles = array_map([Html::class, 'escape'], user_role_names(TRUE));

    $form['account']['roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Roles'),
      '#default_value' => (!$register ? $account->getRoles() : []),
      '#options' => $roles,
      '#access' => $roles && $user->hasPermission('administer permissions'),
    ];

    // Special handling for the inevitable "Authenticated user" role.
    $form['account']['roles'][RoleInterface::AUTHENTICATED_ID] = [
      '#default_value' => TRUE,
      '#disabled' => TRUE,
    ];

    $form['account']['notify'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Notify user of new account'),
      '#access' => $admin_create,
    ];

    $user_preferred_langcode = $register ? $language_interface->getId() : $account->getPreferredLangcode();

    $user_preferred_admin_langcode = $register ? $language_interface->getId() : $account->getPreferredAdminLangcode(FALSE);

    // Is the user preferred language added?
    $user_language_added = FALSE;
    if ($this->languageManager instanceof ConfigurableLanguageManagerInterface) {
      $negotiator = $this->languageManager->getNegotiator();
      $user_language_added = $negotiator && $negotiator->isNegotiationMethodEnabled(LanguageNegotiationUser::METHOD_ID, LanguageInterface::TYPE_INTERFACE);
    }
    $form['language'] = [
      '#type' => $this->languageManager->isMultilingual() ? 'details' : 'container',
      '#title' => $this->t('Language settings'),
      '#open' => TRUE,
      // Display language selector when either creating a user on the admin
      // interface or editing a user account.
      '#access' => !$self_register,
    ];

    $form['language']['preferred_langcode'] = [
      '#type' => 'language_select',
      '#title' => $this->t('Site language'),
      '#languages' => LanguageInterface::STATE_CONFIGURABLE,
      '#default_value' => $user_preferred_langcode,
      '#description' => $user_language_added ? $this->t("This account's preferred language for emails and site presentation.") : $this->t("This account's preferred language for emails."),
      // This is used to explain that user preferred language and entity
      // language are synchronized. It can be removed if a different behavior is
      // desired.
      '#pre_render' => [
        'user_langcode' => [
          $this,
          'alterPreferredLangcodeDescription',
        ],
      ],
    ];

    // Only show the account setting for Administration pages language to users
    // if one of the detection and selection methods uses it.
    $show_admin_language = FALSE;
    if ($account->hasPermission('access administration pages') && $this->languageManager instanceof ConfigurableLanguageManagerInterface) {
      $negotiator = $this->languageManager->getNegotiator();
      $show_admin_language = $negotiator && $negotiator->isNegotiationMethodEnabled(LanguageNegotiationUserAdmin::METHOD_ID);
    }
    $form['language']['preferred_admin_langcode'] = [
      '#type' => 'language_select',
      '#title' => $this->t('Administration pages language'),
      '#languages' => LanguageInterface::STATE_CONFIGURABLE,
      '#default_value' => $user_preferred_admin_langcode,
      '#access' => $show_admin_language,
      '#empty_option' => $this->t('- No preference -'),
      '#empty_value' => '',
    ];

    // User entities contain both a langcode property (for identifying the
    // language of the entity data) and a preferred_langcode property (see
    // above). Rather than provide a UI forcing the user to choose both
    // separately, assume that the user profile data is in the user's preferred
    // language. This entity builder provides that synchronization. For
    // use-cases where this synchronization is not desired, a module can alter
    // or remove this item.
    $form['#entity_builders']['sync_user_langcode'] = '::syncUserLangcode';

    return parent::form($form, $form_state);
  }

  /**
   * Alters the preferred language widget description.
   *
   * @param array $element
   *   The preferred language form element.
   *
   * @return array
   *   The preferred language form element.
   */
  public function alterPreferredLangcodeDescription(array $element): array {
    // Only add to the description if the form element has a description.
    if (isset($element['#description'])) {
      $element['#description'] .= ' ' . $this->t("This is also assumed to be the primary language of this account's profile information.");
    }
    return $element;
  }

  /**
   * Synchronizes preferred language and entity language.
   *
   * @param string $entity_type_id
   *   The entity type identifier.
   * @param \Drupal\user\UserInterface $user
   *   The entity updated with the submitted values.
   * @param array $form
   *   The complete form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function syncUserLangcode(string $entity_type_id, UserInterface $user, array &$form, FormStateInterface $form_state): void {
    $user->getUntranslated()->langcode = $user->preferred_langcode;
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntity(array $form, FormStateInterface $form_state): UserInterface {
    // Change the roles array to a list of enabled roles.
    // @todo Alter the form state as the form values are directly extracted and
    //   set on the field, which throws an exception as the list requires
    //   numeric keys. Allow to override this per field. As this function is
    //   called twice, we have to prevent it from getting the array keys twice.
    if (is_string(key($form_state->getValue('roles')))) {
      $form_state->setValue('roles', array_keys(array_filter($form_state->getValue('roles'))));
    }

    /** @var \Drupal\user\UserInterface $account */
    $account = parent::buildEntity($form, $form_state);

    // Translate the empty value '' of language selects to an unset field.
    foreach ([
      'preferred_langcode',
      'preferred_admin_langcode',
    ] as $field_name) {
      if ($form_state->getValue($field_name) === '') {
        $account->$field_name = NULL;
      }
    }

    // Skip the protected user field constraint since we are not using
    // passwords.
    $account->_skipProtectedUserFieldConstraint = TRUE;
    // Skip mail validation since is not mandatory for WebAuthn.
    $account->_skipUserMailRequiredConstraint = TRUE;

    return $account;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditedFieldNames(FormStateInterface $form_state): array {
    return array_merge([
      'name',
      'mail',
      'timezone',
      'langcode',
      'preferred_langcode',
      'preferred_admin_langcode',
    ], parent::getEditedFieldNames($form_state));
  }

  /**
   * {@inheritdoc}
   */
  protected function flagViolations(EntityConstraintViolationListInterface $violations, array $form, FormStateInterface $form_state): void {
    // Manually flag violations of fields not handled by the form display. This
    // is necessary as entity form displays only flag violations for fields
    // contained in the display.
    $field_names = [
      'name',
      'mail',
      'timezone',
      'langcode',
      'preferred_langcode',
      'preferred_admin_langcode',
    ];
    foreach ($violations->getByFields($field_names) as $violation) {
      [$field_name] = explode('.', $violation->getPropertyPath(), 2);
      $form_state->setErrorByName($field_name, $violation->getMessage());
    }
    parent::flagViolations($violations, $form, $form_state);
  }

}
