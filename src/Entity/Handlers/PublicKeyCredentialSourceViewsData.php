<?php

declare(strict_types=1);


namespace Drupal\webauthn\Entity\Handlers;

use Drupal\views\EntityViewsData;

/**
 * Provides Views data for Public Key Credential Source entities.
 */
class PublicKeyCredentialSourceViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData(): array {
    $data = parent::getViewsData();
    // Additional information for Views integration, such as table joins, can be
    // put here.
    return $data;
  }

}
