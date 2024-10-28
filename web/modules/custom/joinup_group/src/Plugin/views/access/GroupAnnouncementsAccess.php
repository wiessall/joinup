<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Plugin\views\access;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\joinup_group\AnnouncementsAccessTrait;
use Drupal\views\Plugin\views\access\AccessPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;

/**
 * Checks OG or site permissions and if announcements are enabled.
 *
 * @ingroup views_access_plugins
 *
 * @ViewsAccess(
 *   id = "joinup_group_announcements",
 *   title = @Translation("Announcements access"),
 *   help = @Translation("Access will be granted to users with the specified OG or site permission and whether group announcements are enabled."),
 * )
 */
class GroupAnnouncementsAccess extends AccessPluginBase implements CacheableDependencyInterface {

  use AnnouncementsAccessTrait;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return (new static(
      $configuration,
      $plugin_id,
      $plugin_definition
    ))->setJoinupGroupManager($container->get('joinup_group.group_manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account): bool {
    if ($group = $this->groupManager->getGroupFromContext()) {
      return $this->accessAnnouncements($group)->isAllowed();
    }
    throw new \LogicException("The Views 'joinup_group_announcements' can be used only in the context of a Interoperable Europe Portal group.");
  }

  /**
   * {@inheritdoc}
   */
  public function alterRouteDefinition(Route $route): void {
    $route->setRequirement('_joinup_group_announcements', 'TRUE');
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
      'og_role',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return [];
  }

}
