<?php

declare(strict_types=1);


namespace Drupal\webauthn\Entity\Handlers;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access controller for the Public Key Credential Source entity.
 *
 * @see \Drupal\webauthn\Entity\PublicKeyCredentialSource.
 */
class PublicKeyCredentialSourceAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\webauthn\Entity\PublicKeyCredentialSourceInterface $entity */
    switch ($operation) {
      case 'view':
        $has_permission = $account->hasPermission('view public key credential source entities');
        $is_owner = $account->id() === $entity->getOwnerId();

        return AccessResult::allowedIf($is_owner || $has_permission);

      case 'update':
        return AccessResult::forbidden('Public Key Credential Sources cannot be edited.');

      case 'delete':
        $has_permission = $account->hasPermission('delete public key credential source entities');
        $is_owner = $account->id() === $entity->getOwnerId();

        return AccessResult::allowedIf($is_owner || $has_permission);
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add public key credential source entities');
  }

}
