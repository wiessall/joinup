<?php

declare(strict_types=1);

namespace Drupal\joinup_user\ContextProvider;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextProviderInterface;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Sets the user as context on user routes.
 */
class UserRouteContext implements ContextProviderInterface {

  use StringTranslationTrait;

  public function __construct(
    protected readonly RouteMatchInterface $routeMatch,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getRuntimeContexts(array $unqualified_context_ids) {
    $value = NULL;
    if (($route_object = $this->routeMatch->getRouteObject()) && ($route_contexts = $route_object->getOption('parameters')) && isset($route_contexts['user'])) {
      if ($user = $this->routeMatch->getParameter('user')) {
        $value = $user;
      }
    }

    $cacheability = new CacheableMetadata();
    $cacheability->setCacheContexts(['route']);

    $context = new Context(new EntityContextDefinition('entity:user', $this->t('User from URL')), $value);
    $context->addCacheableDependency($cacheability);

    return ['user_route' => $context];
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableContexts() {
    $context = new Context(new EntityContextDefinition('entity:user', $this->t('User from URL')));
    return ['user_route' => $context];
  }

}
