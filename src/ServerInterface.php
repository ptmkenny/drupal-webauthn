<?php

declare(strict_types=1);


namespace Drupal\webauthn;

use Drupal\user\UserInterface;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialSourceRepository;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * Defines a WebAuthn for Drupal.
 */
interface ServerInterface {

  /**
   * Start the attestation ceremony for a given user.
   *
   * @param \Drupal\user\UserInterface $user
   *   The Drupal user.
   *
   * @return \Webauthn\PublicKeyCredentialCreationOptions
   *   The credential creation options.
   */
  public function attestation(UserInterface $user): PublicKeyCredentialCreationOptions;

  /**
   * Handle attestation response.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user instance.
   * @param string $response
   *   The authenticator response (JSON encoded).
   *
   * @return \Webauthn\PublicKeyCredentialSource|null
   *   Returns the credential source on success or NULL on error.
   */
  public function handleAttestation(UserInterface $user, string $response): ?PublicKeyCredentialSource;

  /**
   * Start the assertion ceremony for a given user.
   *
   * @param \Drupal\user\UserInterface $user
   *   The drupal user.
   *
   * @return \Webauthn\PublicKeyCredentialRequestOptions
   *   The credential request options.
   */
  public function assertion(UserInterface $user): PublicKeyCredentialRequestOptions;

  /**
   * Handle assertion response.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user instance.
   * @param string $response
   *   The authenticator response (JSON encoded).
   *
   * @return \Webauthn\PublicKeyCredentialSource|null
   *   Returns the credential source on success or NULL on error.
   */
  public function handleAssertion(UserInterface $user, string $response): ?PublicKeyCredentialSource;

  /**
   * Get a relying party object.
   *
   * @return \Webauthn\PublicKeyCredentialRpEntity
   *   THe relying party object.
   */
  public function getRp(): PublicKeyCredentialRpEntity;

  /**
   * Find user entity by username.
   *
   * @param string $name
   *   The user name.
   *
   * @return \Webauthn\PublicKeyCredentialUserEntity|null
   *   The user entity if found, NULL otherwise.
   */
  public function findUserEntityByUsername(string $name): ?PublicKeyCredentialUserEntity;

  /**
   * Find user entity by username.
   *
   * @param string $userHandle
   *   The user handle (uuid).
   *
   * @return \Webauthn\PublicKeyCredentialUserEntity|null
   *   The user entity if found, NULL otherwise.
   */
  public function findUserEntityByUserHandle(string $userHandle): ?PublicKeyCredentialUserEntity;

  /**
   * Create user entity from a Drupal user.
   *
   * @param \Drupal\user\UserInterface|\Drupal\Core\Session\AccountInterface $user
   *
   * @return \Webauthn\PublicKeyCredentialUserEntity
   */
  public function createUserEntity(UserInterface $user): PublicKeyCredentialUserEntity;

  /**
   * Get the credential source repository.
   *
   * @return \Webauthn\PublicKeyCredentialSourceRepository
   *   The credential source repository.
   */
  public function getPublicKeyCredentialSourceRepository(): PublicKeyCredentialSourceRepository;

}
