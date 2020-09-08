<?php

declare(strict_types=1);


namespace Drupal\webauthn\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;
use Webauthn\PublicKeyCredentialSource as PKCredentialSource;

/**
 * Provides an interface for defining Public Key Credential Source entities.
 */
interface PublicKeyCredentialSourceInterface extends ContentEntityInterface, EntityOwnerInterface {

  /**
   * Create instance from source object.
   *
   * @param \Webauthn\PublicKeyCredentialSource $source
   *   The source object.
   *
   * @return static
   *   A new instance.
   */
  public static function createFromSourceObject(PKCredentialSource $source): self;

  /**
   * Get the actual source object.
   *
   * @return \Webauthn\PublicKeyCredentialSource
   *   The source object.
   */
  public function getPublicKeyCredentialSource(): PKCredentialSource;

  /**
   * Get the public key ID.
   *
   * @return string|null
   *   The public key ID.
   */
  public function getPublicKeyCredentialId(): ?string;

  /**
   * Get the device AAGUID.
   *
   * @return string|null
   *   The device AAGUID.
   */
  public function getDeviceId(): ?string;

  /**
   * Get the number of public key usages.
   *
   * @return int
   *   The number of usages, by default 0 when the key is created.
   */
  public function getCounter(): int;

}
