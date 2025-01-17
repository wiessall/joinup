<?php

declare(strict_types=1);

namespace Drupal\joinup_licence\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\joinup_licence\Entity\LicenceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Displays a deprecation warning in a block.
 *
 * @Block(
 *  id = "licence_deprecated_message_block",
 *  admin_label = @Translation("Licence deprecated term message"),
 * )
 */
class LicenceDeprecatedMessageBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected readonly CurrentRouteMatch $currentRouteMatch,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
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
  public function build() {
    $route_name = $this->currentRouteMatch->getRouteName();
    if ($route_name !== 'entity.node.canonical') {
      return [];
    }

    $term = $this->currentRouteMatch->getParameter('node');
    if (!$term instanceof LicenceInterface) {
      return [];
    }

    $deprecated = (bool) $term->get('deprecated')->value;
    if (!$deprecated) {
      return [];
    }
    $message = $this->t('This licence is deprecated and will not be selected for new distributions.');
    $build = [
      '#theme' => 'status_messages',
      '#message_list' => [
        'warning' => [$message],
      ],
      '#status_headings' => [
        'warning' => $this->t('Warning message'),
      ],
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    $cache_contexts = parent::getCacheContexts();
    return Cache::mergeContexts($cache_contexts, ['route']);
  }

}
