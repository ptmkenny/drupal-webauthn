<?php

namespace Drupal\webauthn\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteBuildEvent;
use Drupal\Core\Routing\RoutingEvents;
use Drupal\webauthn\Form\PublicKeyCredentialRequestForm;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Alters user.login route to use passwordless authentication form.
 */
class UserRouteAlterSubscriber implements EventSubscriberInterface {

  /**
   * The webauthn settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  private $config;

  /**
   * UserRouteAlterSubscriber constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->config = $config_factory->get('webauthn.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[RoutingEvents::ALTER][] = 'onRoutingAlterReplaceLogin';

    return $events;
  }

  /**
   * Replace login form.
   *
   * @param \Drupal\Core\Routing\RouteBuildEvent $event
   *   The event to process.
   */
  public function onRoutingAlterReplaceLogin(RouteBuildEvent $event) {
    $routes = $event->getRouteCollection();
    if (($route = $routes->get('user.login'))
      && !empty($this->config->get('replace_login_form'))) {
      $route->setDefault('_form', PublicKeyCredentialRequestForm::class);
    }
  }

}
