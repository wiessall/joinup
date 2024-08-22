<?php

declare(strict_types=1);

namespace Drupal\joinup_search\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a Search clear filters  block.
 *
 * This block is used in the sidebar Search Page. It allows to
 * do a clear filters in search.
 *
 * @Block(
 *   id = "joinup_search_clear_filters",
 *   admin_label = @Translation("Search clear filters")
 * )
 */
class ClearFiltersSearchBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a new ProposedEntitiesBlock object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $pluginId
   *   The plugin_id for the plugin instance.
   * @param mixed $pluginDefinition
   *   The plugin implementation definition.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   */
  public function __construct(
    array $configuration,
    string $pluginId,
    $pluginDefinition,
    protected RequestStack $requestStack,
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
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $request = $this->requestStack->getMainRequest();
    $query_params = $request->query->all();
    $arr = array_diff_key($query_params, array_flip(['keys', 'sort_by']));
    foreach (array_keys($arr) as $key) {
      unset($query_params[$key]);
    }

    return [
      '#type' => 'link',
      '#title' => $this->t('Clear filters'),
      '#url' => Url::fromRoute('<current>', [], ['query' => $query_params]),
      '#attributes' => [
        'class' => ['btn', 'btn-outline-brand', 'w-100', 'mb-4'],
        'rel' => 'nofollow',
        'role' => 'button',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge(): int {
    return 0;
  }

}
