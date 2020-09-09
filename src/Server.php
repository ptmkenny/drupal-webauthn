<?php

declare(strict_types=1);


namespace Drupal\webauthn;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\user\UserInterface;
use Exception;
use Nyholm\Psr7\Factory\Psr17Factory;
use RuntimeException;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\RequestStack;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialSourceRepository;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\Server as WebAuthnServer;

/**
 * Drupal implementation of WebAuthn server.
 *
 * @uses \Drupal\webauthn\Entity\PublicKeyCredentialSourceRepository
 */
class Server implements ServerInterface {

  use StringTranslationTrait, LoggerChannelTrait;

  /**
   * The site configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  private $config;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  private $privateTempStore;

  /**
   * @var \Webauthn\PublicKeyCredentialSourceRepository
   */
  private $pkCredentialSourceRepository;

  /**
   * @var \Symfony\Component\HttpFoundation\Request
   */
  private $request;

  /**
   * Server constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity-type manager service.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $private_temp_store
   *   The private temp-store service.
   * @param \Webauthn\PublicKeyCredentialSourceRepository $pk_credential_source_repository
   * @param \Symfony\Component\HttpFoundation\RequestStack $request
   */
  public function __construct(ConfigFactoryInterface $configFactory, EntityTypeManagerInterface $entity_type_manager, PrivateTempStoreFactory $private_temp_store, PublicKeyCredentialSourceRepository $pk_credential_source_repository, RequestStack $request) {
    $this->config = $configFactory->get('webauthn.settings');
    $this->entityTypeManager = $entity_type_manager;
    $this->privateTempStore = $private_temp_store->get('webauthn');
    $this->pkCredentialSourceRepository = $pk_credential_source_repository;
    $this->request = $request->getCurrentRequest();
  }

  /**
   * {@inheritdoc}
   */
  public function attestation(UserInterface $user): PublicKeyCredentialCreationOptions {
    $server = new WebAuthnServer($this->getRp(), $this->pkCredentialSourceRepository, NULL);
    $user_entity = $this->createUserEntity($user);
    $exclude_credentials = [];

    if (!$user->isNew()) {
      // Get the list of authenticators associated to the user
      $credentialSources = $this->pkCredentialSourceRepository->findAllForUserEntity($user_entity);
      // Convert the Credential Sources into Public Key Credential Descriptors
      $exclude_credentials = array_map(static function (PublicKeyCredentialSource $credential) {
        return $credential->getPublicKeyCredentialDescriptor();
      }, $credentialSources);
    }

    $mode = PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE;
    $options = $server->generatePublicKeyCredentialCreationOptions(
      $user_entity,
      $mode,
      $exclude_credentials
    );
    // Store the options to be use later during attestation verification.
    $this->privateTempStore->set('attestation', Json::encode($options));

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function getRp(): PublicKeyCredentialRpEntity {
    return new PublicKeyCredentialRpEntity(
      $this->config->get('relying_party.name'),
      $this->config->get('relying_party.id'),
      $this->config->get('relying_party.logo')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getPublicKeyCredentialSourceRepository(): PublicKeyCredentialSourceRepository {
    return $this->pkCredentialSourceRepository;
  }

  /**
   * {@inheritdoc}
   */
  public function createUserEntity(UserInterface $user): PublicKeyCredentialUserEntity {
    return new PublicKeyCredentialUserEntity(
      $user->getAccountName(),
      $user->uuid(),
      $user->getDisplayName() ?? $user->getAccountName()
    // @TODO Add base64 encoded user picture.
    );
  }

  /**
   * {@inheritdoc}
   */
  public function handleAttestation(UserInterface $user, string $response): ?\Webauthn\PublicKeyCredentialSource {
    try {
      // Convert Symfony request into PSR-7 compatible request.
      // @link https://symfony.com/doc/3.4/components/psr7.html
      $psr17Factory = new Psr17Factory();
      $psrHttpFactory = new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);
      $request = $psrHttpFactory->createRequest($this->request);

      $data = Json::decode($this->privateTempStore->get('attestation'));
      /** @var \Webauthn\PublicKeyCredentialCreationOptions $options */
      $options = PublicKeyCredentialCreationOptions::createFromArray($data);

      if ($options === NULL) {
        throw new RuntimeException($this->t('No attestation options found for handle :handle', [
          ':handle' => $user->uuid(),
        ]));
      }

      $server = new WebAuthnServer($this->getRp(), $this->pkCredentialSourceRepository, NULL);
      return $server->loadAndCheckAttestationResponse($response, $options, $request);
    }
    catch (Exception $e) {
      $this->getLogger('webauthn')->error($e->getMessage());
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function findUserEntityByUsername(string $name): ?PublicKeyCredentialUserEntity {
    $storage = $this->entityTypeManager->getStorage('user');
    /** @var \Drupal\user\UserInterface[] $user */
    $user = $storage->loadByProperties(['name' => $name]);

    if (empty($user)) {
      return NULL;
    }

    return $this->createUserEntity(reset($user));
  }

  /**
   * {@inheritdoc}
   */
  public function findUserEntityByUserHandle(string $userHandle): ?PublicKeyCredentialUserEntity {
    $storage = $this->entityTypeManager->getStorage('user');
    /** @var \Drupal\user\UserInterface[] $user */
    $user = $storage->loadByProperties(['uuid' => $userHandle]);

    if (empty($user)) {
      return NULL;
    }

    return $this->createUserEntity(reset($user));
  }

  /**
   * {@inheritdoc}
   */
  public function assertion(UserInterface $user): PublicKeyCredentialRequestOptions {
    $server = new WebAuthnServer($this->getRp(), $this->pkCredentialSourceRepository, NULL);
    $user_entity = $this->createUserEntity($user);
    $sources = $this->pkCredentialSourceRepository->findAllForUserEntity($user_entity);
    $allowed_credentials = array_filter(array_map(static function (?PublicKeyCredentialSource $credential) {
      return $credential ? $credential->getPublicKeyCredentialDescriptor() : NULL;
    }, $sources));

    $options = $server->generatePublicKeyCredentialRequestOptions(
      PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_PREFERRED,
      $allowed_credentials
    );
    // Store the options to be use later during assertion verification.
    $this->privateTempStore->set('assertion', Json::encode($options));

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function handleAssertion(UserInterface $user, string $response): ?PublicKeyCredentialSource {
    try {
      // Convert Symfony request into PSR-7 compatible request.
      // @link https://symfony.com/doc/3.4/components/psr7.html
      $psr17Factory = new Psr17Factory();
      $psrHttpFactory = new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);
      $request = $psrHttpFactory->createRequest($this->request);

      $data = Json::decode($this->privateTempStore->get('attestation'));
      /** @var \Webauthn\PublicKeyCredentialRequestOptions $options */
      $options = PublicKeyCredentialRequestOptions::createFromArray($data);

      if ($options === NULL) {
        throw new RuntimeException($this->t('No attestation options found for handle :handle', [
          ':handle' => $user->uuid(),
        ]));
      }

      $entity = $this->createUserEntity($user);
      $server = new WebAuthnServer($this->getRp(), $this->pkCredentialSourceRepository, NULL);
      return $server->loadAndCheckAssertionResponse(
        $response,
        $options,
        $entity,
        $request);
    }
    catch (Exception $e) {
      $this->getLogger('webauthn')->error($e->getMessage());
    }

    return NULL;
  }

}
