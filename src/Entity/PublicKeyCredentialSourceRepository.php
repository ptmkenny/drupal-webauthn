<?php

declare(strict_types=1);


namespace Drupal\webauthn\Entity;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Webauthn\PublicKeyCredentialSource as PKCredentialSource;
use Webauthn\PublicKeyCredentialSourceRepository as BasePublicKeyCredentialSourceRepository;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * Drupal implementation for credential source repository.
 */
class PublicKeyCredentialSourceRepository implements BasePublicKeyCredentialSourceRepository {

  use StringTranslationTrait;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  private $storage;

  /**
   * PublicKeyCredentialSourceRepository constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity-type manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->storage = $entity_type_manager->getStorage('public_key_credential_source');
  }

  /**
   * {@inheritdoc}
   */
  public function findOneByCredentialId(string $publicKeyCredentialId): ?PKCredentialSource {
    /** @var \Drupal\webauthn\Entity\PublicKeyCredentialSourceInterface[] $entity */
    $entity = $this->storage->loadByProperties(['publicKeyCredentialId' => $publicKeyCredentialId]);

    if (empty($entity)) {
      return NULL;
    }

    $entity = reset($entity);

    return $entity->getPublicKeyCredentialSource();
  }

  /**
   * {@inheritdoc}
   */
  public function findAllForUserEntity(PublicKeyCredentialUserEntity $publicKeyCredentialUserEntity): array {
    $query = $this->storage->getQuery();
    $results = $query->condition('userHandle.entity.name', $publicKeyCredentialUserEntity->getName())
      ->execute();

    if (empty($results)) {
      return [];
    }

    return array_map(static function (PublicKeyCredentialSource $entity) {
      return $entity->getPublicKeyCredentialSource();
    }, $this->storage->loadMultiple($results));
  }

  /**
   * {@inheritdoc}
   */
  public function saveCredentialSource(PKCredentialSource $publicKeyCredentialSource): void {
    $entity = PublicKeyCredentialSource::createFromSourceObject($publicKeyCredentialSource);
    $storage = $this->entityTypeManager->getStorage('user');
    /** @var \Drupal\user\UserInterface[] $user */
    $user = $storage->loadByProperties(['uuid' => $publicKeyCredentialSource->getUserHandle()]);

    if (empty($user)) {
      throw new \RuntimeException($this->t('Cannot save credential source :key, the user :handle does not exists.', [
        ':key' => $publicKeyCredentialSource->getPublicKeyCredentialId(),
        ':handle' => $publicKeyCredentialSource->getUserHandle(),
      ]));
    }

    $entity->setOwner(reset($user));
    $entity->save();
  }

}
