<?php

declare(strict_types=1);

namespace Drupal\joinup_solution\Plugin\views\access;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\ResettableStackedRouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\views\Plugin\views\access\AccessPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;

/**
 * Access plugin that provides access to manage solutions.
 *
 * @ingroup views_access_plugins
 *
 * @ViewsAccess(
 *   id = "manage_solutions",
 *   title = @Translation("Manage solutions"),
 *   help = @Translation("Access will be granted to users with 'manage solutions' permission."),
 * )
 */
class ManageSolutionsAccess extends AccessPluginBase implements CacheableDependencyInterface {

  public function __construct(
    array $configuration,
    readonly string $plugin_id,
    readonly mixed $plugin_definition,
    protected readonly ResettableStackedRouteMatchInterface $routeMatch,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function alterRouteDefinition(Route $route): void {
    $parameters = $route->getOption('parameters');
    $parameters['node']['bundle'] = ['collection'];
    $route->setOption('parameters', $parameters);
    $route->setRequirement('_permission', 'manage solutions');
    $route->setRequirement('_archived_group', 'TRUE');
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account): bool {
    if (!$account->hasPermission('manage solutions')) {
      return FALSE;
    }

    if ($node = $this->routeMatch->getParameter('node')) {
      if ($node instanceof NodeInterface) {
        return TRUE;
      }
      elseif (is_numeric($node)) {
        $node = $this->entityTypeManager->getStorage('node')->load($node);
        if ($node instanceof NodeInterface) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge(): int {
    return Cache::PERMANENT;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return [
      'user.permissions',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return [];
  }

}
