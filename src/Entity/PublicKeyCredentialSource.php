<?php

declare(strict_types=1);

namespace Drupal\webauthn\Entity;

use Base64Url\Base64Url;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\UserInterface;
use Webauthn\PublicKeyCredentialSource as PKCredentialSource;
use Webauthn\TrustPath\TrustPath;

/**
 * Defines the Public Key Credential Source entity.
 *
 * The entity implementation only serves as wrapper to integrate the object
 * with Drupal. Since Drupal does not supports entity mapping like Doctrine
 * and PHP does not support multiple inheritance we cannot extend both, Drupal
 * entity and WebAuthn model. For that we wrap the model with an entity and
 * define proper interface methods.
 *
 * @ingroup webauthn
 *
 * @ContentEntityType(
 *   id = "public_key_credential_source",
 *   label = @Translation("Public Key Credential Source"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\webauthn\Entity\Handlers\PublicKeyCredentialSourceListBuilder",
 *     "views_data" = "Drupal\webauthn\Entity\Handlers\PublicKeyCredentialSourceViewsData",
 *     "form" = {
 *       "delete" = "Drupal\webauthn\Form\PublicKeyCredentialSourceDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\webauthn\Entity\Handlers\PublicKeyCredentialSourceHtmlRouteProvider",
 *     },
 *     "access" = "Drupal\webauthn\Entity\Handlers\PublicKeyCredentialSourceAccessControlHandler",
 *   },
 *   base_table = "public_key_credential_source",
 *   data_table = "public_key_credential_source_field_data",
 *   translatable = FALSE,
 *   admin_permission = "administer public key credential source entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "uid" = "userHandle"
 *   },
 *   links = {
 *     "canonical" = "/admin/people/public_key_credential_source/{public_key_credential_source}",
 *     "delete-form" = "/admin/people/public_key_credential_source/{public_key_credential_source}/delete",
 *     "collection" = "/admin/people/public_key_credential_source",
 *   }
 * )
 */
final class PublicKeyCredentialSource extends ContentEntityBase implements PublicKeyCredentialSourceInterface {

  /**
   * The source instance.
   *
   * @var \Webauthn\PublicKeyCredentialSource
   */
  private $sourceObject;

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);

    $storage = \Drupal::entityTypeManager()->getStorage('user');
    $user_handle = $values[$storage_controller->getEntityType()->getKey('uid')];
    $user = $storage->loadByProperties(['uuid' => $user_handle]);

    if (!empty($user)) {
      $values[$storage_controller->getEntityType()
        ->getKey('uid')] = reset($user);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function createFromSourceObject(PKCredentialSource $source): PublicKeyCredentialSourceInterface {
    $values = $source->jsonSerialize();
    /** @var \Drupal\Component\Uuid\UuidInterface $uuid */
    $uuid = \Drupal::service('uuid');
    $values += [
      'uuid' => $uuid->generate(),
    ];
    $instance = new static([], 'public_key_credential_source');
    foreach ($values as $field_name => $value) {
      $instance->set($field_name, $value);
    }

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['publicKeyCredentialId'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Public Key Credential ID'))
      ->setDescription(t('The credential id.'))
      ->setRequired(TRUE);

    $fields['type'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Type'))
      ->setDescription(t('The credential type.'))
      ->setRequired(TRUE);

    $fields['transports'] = BaseFieldDefinition::create('map')
      ->setLabel(new TranslatableMarkup('Transports'))
      ->setDescription(t('A list of supported transport modes.'))
      ->setRequired(TRUE);

    $fields['attestationType'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Attestation Type'))
      ->setDescription(t('The attestation type used to create the credential.'))
      ->setRequired(TRUE);

    $fields['trustPath'] = BaseFieldDefinition::create('trust_path')
      ->setLabel(new TranslatableMarkup('Trust Path'))
      ->setDescription(t('Trusted path data, usually certificate or ECDAA keys parameters.'))
      ->setRequired(TRUE);

    // This can hold "00000000-0000-0000-0000-000000000000" which is a
    // valid UUIDv4 value. This is caused by the
    // AttestationConveyancePreference not set to "direct"
    // https://qiita.com/i7a7467/items/694fd9a30df1fb87db40
    $fields['aaguid'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Authenticator Attestation ID'))
      ->setDescription(t('An unique identifier of authenticator model.'))
      ->setSettings([
        'max_length' => 128,
        'is_ascii' => TRUE,
      ])
      ->setRequired(TRUE);

    $fields['credentialPublicKey'] = BaseFieldDefinition::create('string_long')
      ->setLabel(new TranslatableMarkup('Public Key'))
      ->setDescription(t('The credential public key data.'))
      ->setRequired(TRUE);

    $fields['userHandle'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Owner'))
      ->setDescription(t('The user ID which this credential belongs to.'))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default');

    $fields['counter'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Counter'))
      ->setDescription(t('Incremented for each successful authenticator assertion.'))
      ->setDefaultValue(0)
      ->setRequired(TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get($this->getEntityType()->getKey('uid'))->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set($this->getEntityType()->getKey('uid'), $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set($this->getEntityType()->getKey('uid'), $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPublicKeyCredentialSource(): PKCredentialSource {
    if (!isset($this->sourceObject)) {
      $this->sourceObject = PKCredentialSource::createFromArray([
        'publicKeyCredentialId' => Base64Url::encode($this->get('publicKeyCredentialId')->value),
        'type' => $this->get('type')->value,
        'transports' => $this->get('transports')->value,
        'attestationType' => $this->get('attestationType')->value,
        'trustPath' => TrustPath::createFromArray($this->get('trustPath')->value),
        'aaguid' => $this->get('aaguid')->value,
        'credentialPublicKey' => Base64Url::encode($this->get('credentialPublicKey')->value),
        'userHandle' => Base64Url::encode($this->getOwner()->uuid()),
        'counter' => $this->get('counter')->value,
      ]);
    }

    return $this->sourceObject;
  }

  /**
   * {@inheritdoc}
   */
  public function getPublicKeyCredentialId(): ?string {
    return $this->get('publicKeyCredentialId')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getDeviceId(): ?string {
    return $this->get('aaguid')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getCounter(): int {
    return (int) ($this->get('counter')->value ?? 0);
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get($this->getEntityType()->getKey('uid'))->entity;
  }

}
