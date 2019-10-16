<?php

namespace Drupal\webauthn;

use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Webauthn\PublicKeyCredentialRpEntity;

/**
 * Factory service to construct RP object.
 */
class RelyingPartyFactory {

  /**
   * The site configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  private $config;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request|null
   */
  private $request;

  /**
   * RelyingPartyFactory constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   */
  public function __construct(ConfigFactoryInterface $configFactory, RequestStack $requestStack) {
    $this->config = $configFactory->get('system.site');
    $this->request = $requestStack->getCurrentRequest();
  }

  /**
   * Get a relying party object.
   *
   * @return \Webauthn\PublicKeyCredentialRpEntity
   *   THe relying party object.
   */
  public function get(): PublicKeyCredentialRpEntity {
    return new PublicKeyCredentialRpEntity(
      $this->config->get('name'),
      $this->request ? $this->request->getHost() : $this->config->get('uuid')
    );
  }

}
