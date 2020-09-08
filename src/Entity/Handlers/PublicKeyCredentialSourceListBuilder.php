<?php

declare(strict_types=1);


namespace Drupal\webauthn\Entity\Handlers;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Defines a class to build a listing of Public Key Credential Source entities.
 *
 * @ingroup webauthn
 */
class PublicKeyCredentialSourceListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('ID');
    $header['device'] = $this->t('Device');
    $header['counter'] = $this->t('Counter');
    $header['user'] = $this->t('User');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var \Drupal\webauthn\Entity\PublicKeyCredentialSourceInterface $entity */
    $row['id'] = $entity->getPublicKeyCredentialId();
    $row['device'] = $entity->getDeviceId();
    $row['counter'] = $entity->getCounter();
    $row['user'] = $entity->getOwner()->toLink();
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getOperations(EntityInterface $entity) {
    $operations = parent::getOperations($entity);
    // Do not display edit operation since this entity-type cannot be edited
    // from the UI.
    unset($operations['edit']);
    return $operations;
  }

}
