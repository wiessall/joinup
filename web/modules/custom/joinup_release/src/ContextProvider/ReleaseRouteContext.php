<?php

declare(strict_types=1);

namespace Drupal\joinup_release\ContextProvider;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextProviderInterface;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\joinup_distribution\Entity\DistributionInterface;
use Drupal\joinup_release\Entity\ReleaseInterface;

/**
 * Sets the current release as a context on release routes.
 */
class ReleaseRouteContext implements ContextProviderInterface {

  use StringTranslationTrait;

  /**
   * The route match object.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected RouteMatchInterface $routeMatch;

  /**
   * Constructs a new AssetReleaseRouteContext.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match object.
   */
  public function __construct(RouteMatchInterface $route_match) {
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public function getRuntimeContexts(array $unqualified_context_ids): array {
    $result = [];
    $context_definition = new EntityContextDefinition('entity:node', NULL, FALSE);
    $value = NULL;
    if (($route_object = $this->routeMatch->getRouteObject()) && ($route_contexts = $route_object->getOption('parameters'))) {
      if (
        isset($route_contexts['node'])
        && ($release = $this->routeMatch->getParameter('node'))
        && ($release instanceof ReleaseInterface)
      ) {
        $value = $release;
      }
      elseif (
        isset($route_contexts['node'])
        && ($node = $this->routeMatch->getParameter('node'))
        && $node instanceof DistributionInterface
      ) {
        $parent = $node->distribution_parent->entity;
        if ($parent instanceof ReleaseInterface) {
          $value = $parent;
        }
      }
    }

    $cacheability = new CacheableMetadata();
    $cacheability->setCacheContexts(['route']);

    $context = new Context($context_definition, $value);
    $context->addCacheableDependency($cacheability);

    $result['release'] = $context;

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableContexts(): array {
    $context = new Context(new EntityContextDefinition('entity:node', $this->t('Release from URL')));
    return ['release' => $context];
  }

}
