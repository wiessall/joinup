<?php

declare(strict_types=1);

namespace Drupal\eira\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block showing information about deprecated EIRA building blocks.
 *
 * @Block(
 *  id = "eira_deprecated_term_block",
 *  admin_label = @Translation("Eira deprecated term message"),
 * )
 */
class EiraDeprecatedTermBlock extends BlockBase implements ContainerFactoryPluginInterface {

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
    if ($route_name !== 'entity.taxonomy_term.canonical') {
      return [];
    }

    $term = $this->currentRouteMatch->getParameter('taxonomy_term');
    if (empty($term) || $term->bundle() !== 'eira') {
      return [];
    }

    $deprecated = (bool) $term->get('field_taxonomy_deprecated')->value;
    if (!$deprecated) {
      return [];
    }

    $taxonomy_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $results = $taxonomy_storage->getQuery()->accessCheck(FALSE)
      ->condition('field_taxonomy_replaces', $term->id())
      ->condition('vid', 'eira')
      ->execute();

    if (empty($results)) {
      $message = $this->t('This building block is deprecated, and should not be used in new development.');
    }
    else {
      $replacement_id = reset($results);
      $replacement = $taxonomy_storage->load($replacement_id);
      $message = $this->t('This building block is deprecated, and should not be used in new development. Consider using @title (@uri) instead.', [
        '@uri' => $replacement->getUri(),
        '@title' => $replacement->label(),
      ]);
    }

    return [
      '#theme' => 'status_messages',
      '#message_list' => [
        'warning' => [$message],
      ],
      '#status_headings' => [
        'warning' => $this->t('Warning message'),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    $cache_contexts = parent::getCacheContexts();
    return Cache::mergeContexts($cache_contexts, ['route']);
  }

}
