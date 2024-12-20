<?php

declare(strict_types=1);

namespace Drupal\eira\Plugin\Block;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an EIRA properties block.
 *
 * @Block(
 *   id = "eira_properties_block",
 *   admin_label = @Translation("EIRA properties block"),
 *   category = @Translation("EIRA")
 * )
 */
class EiraPropertiesBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    readonly string $plugin_id,
    readonly mixed $plugin_definition,
    protected readonly CurrentRouteMatch $currentRouteMatch,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): EiraPropertiesBlock {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $term = $this->currentRouteMatch->getParameter('taxonomy_term');
    if (!$term instanceof TermInterface) {
      return [
        '#markup' => $this->t('Not supported for non Term entities.'),
      ];
    }

    $data = Json::decode($term->get('extra_properties')->value ?? '') ?? [];

    $rows = array_map(function (array $row): array {
      $row['value'] = $row['value']['value'];
      return $row;
    }, $data['rows'] ?? []);

    if (empty($rows)) {
      return [];
    }

    $build = [
      '#type' => 'container',
      'namespaces_table' => [
        '#theme' => 'table',
        '#header' => [
          'namespace' => $this->t('Namespace'),
          'uri' => $this->t('URI'),
        ],
        '#rows' => array_values($data['namespaces'] ?? []),
      ],
      'properties_table' => [
        '#theme' => 'table',
        '#header' => [
          'property' => $this->t('Property'),
          'value' => $this->t('Value'),
        ],
        '#rows' => $rows,
      ],
    ];

    $cacheableMetadata = CacheableMetadata::createFromRenderArray($build);
    $cacheableMetadata->addCacheableDependency($term);
    $cacheableMetadata->applyTo($build);

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return Cache::mergeContexts(parent::getCacheContexts(), ['route']);
  }

}
