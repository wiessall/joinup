<?php

declare(strict_types = 1);

namespace Drupal\joinup_group\Plugin\views\access;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\joinup_group\JoinupGroupManagerInterface;
use Drupal\views\Plugin\views\access\AccessPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;

/**
 * Checks if view has some results.
 *
 * @ingroup views_access_plugins
 *
 * @ViewsAccess(
 *   id = "joinup_group_results_required",
 *   title = @Translation("Group - results required for view"),
 *   help = @Translation("View must contains some results."),
 * )
 */
class GroupResultsRequiredAccess extends AccessPluginBase implements CacheableDependencyInterface {

  /**
   * Constructs a GroupResultsRequiredAccess object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\joinup_group\JoinupGroupManagerInterface $groupManager
   *   Group manager.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected JoinupGroupManagerInterface $groupManager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return (new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('joinup_group.group_manager')
    ));
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account): bool {
    if (!$this->groupManager->getGroupFromContext()) {
      throw new \LogicException("The Views 'joinup_group_results_required' can be used only in the context of a Joinup group.");
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function alterRouteDefinition(Route $route): void {
    $route->setRequirement('_joinup_group_results_required', 'TRUE');
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
    return ['og_group_context'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    if (!$group = $this->groupManager->getGroupFromContext()) {
      return [];
    }

    return Cache::buildTags('og-group-content', $group->getCacheTags());
  }

}
