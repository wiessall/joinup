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
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a new ProposedEntitiesBlock object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(array $configuration, string $plugin_id, $plugin_definition, RequestStack $request_stack) {
    $this->requestStack = $request_stack;
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
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $request = $this->requestStack->getMainRequest();
    $query_params = $request->query->all();
    $arr = array_diff_key($query_params, array_flip(['keys', 'sort_by']));
    foreach (array_keys($arr) as $key) {
      unset($query_params[$key]);
    }

    return [
      '#type' => 'link',
      '#title' => $this->t('Clear filters'),
      '#url' => Url::fromRoute('view.search.page_1', [], ['query' => $query_params]),
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
  public function getCacheMaxAge() {
    return 0;
  }

}
