<?php

declare(strict_types=1);

namespace Drupal\joinup_solution\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\joinup_release\Entity\ReleaseInterface;
use Drupal\joinup_solution\Entity\SolutionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A block showing the release date.
 *
 * @todo This should probably move to joinup_release.
 */
#[Block(
  id: 'solution_release_date',
  admin_label: new TranslatableMarkup('Release date'),
  category: new TranslatableMarkup('Solution'),
)]
class ReleaseDateBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $pluginId,
    mixed $pluginDefinition,
    protected readonly RouteMatchInterface $routeMatch,
    protected readonly DateFormatterInterface $dateFormatter,
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
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
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $release = $this->getLatestRelease();
    if (!$release) {
      return [];
    }

    $output = $release->get('created')->view([
      'type' => 'timestamp',
      'label' => 'above',
      'settings' => [
        'date_format' => 'date_only',
      ],
    ]);

    $output['#title'] = $this->t('Release date');
    return $output;
  }

  /**
   * Gets the latest release.
   *
   * @return \Drupal\joinup_release\Entity\ReleaseInterface|null
   *   The latest release.
   */
  protected function getLatestRelease(): ?ReleaseInterface {
    $entity = $this->routeMatch->getParameter('node');

    if ($entity instanceof SolutionInterface) {
      return $entity->getLatestRelease();
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return Cache::mergeContexts(parent::getCacheContexts(), ['route']);
  }

  /**
   * {@inheritdoc}
   */
  public function blockAccess(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIf((bool) $this->getLatestRelease())
      ->addCacheContexts(['og_group_context'])
      ->addCacheTags(['node_list:solution', 'node_list:release']);
  }

}
