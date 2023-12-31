<?php

declare(strict_types=1);

namespace Drupal\webauthn\Routing;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\webauthn\Form\PublicKeyCredentialRequestForm;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Register dynamic routes for user register/login operations.
 */
class RouteProvider {

  /**
   * The webauthn settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  private ImmutableConfig $config;

  /**
   * RouteProvider constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->config = $config_factory->get('webauthn.settings');
  }

  /**
   * Defines register/login routes.
   */
  public function routes(): RouteCollection {
    $route_collection = new RouteCollection();

    if (empty($this->config->get('replace_registration_form'))) {
      $route = (new Route('/webauthn/register'))
        ->setDefaults([
          '_entity_form' => 'user.webauthn',
          '_title' => 'Create new account',
        ])
        ->setRequirements([
          '_access_user_register' => 'TRUE',
        ]);
      $route_collection->add('webauthn.register', $route);
    }

    if (empty($this->config->get('replace_login_form'))) {
      $route = (new Route('/webauthn/login'))
        ->setDefaults([
          '_form' => PublicKeyCredentialRequestForm::class,
          '_title' => 'Log in',
        ])
        ->setRequirements([
          '_user_is_logged_in' => 'FALSE',
        ]);
      $route_collection->add('webauthn.login', $route);
    }

    return $route_collection;
  }

}
