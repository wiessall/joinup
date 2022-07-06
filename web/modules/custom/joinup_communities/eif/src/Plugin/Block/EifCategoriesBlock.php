<?php

declare(strict_types = 1);

namespace Drupal\eif\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Url;
use Drupal\eif\EifInterface;
use Drupal\sparql_entity_storage\Driver\Database\sparql\ConnectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a navigator for EIF Toolbox solutions page navigator.
 *
 * @Block(
 *   id = "eif_categories",
 *   admin_label = @Translation("EIF categories"),
 *   category = @Translation("EIF"),
 * )
 */
class EifCategoriesBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The SPARQL connection.
   *
   * @var \Drupal\sparql_entity_storage\Driver\Database\sparql\ConnectionInterface
   */
  protected $sparql;

  /**
   * The current route match service.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $routeMatch;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new block plugin instance.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\sparql_entity_storage\Driver\Database\sparql\ConnectionInterface $sparql
   *   The SPARQL connection.
   * @param \Drupal\Core\Routing\CurrentRouteMatch $route_match
   *   The current route match service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(array $configuration, string $plugin_id, $plugin_definition, ConnectionInterface $sparql, CurrentRouteMatch $route_match, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->sparql = $sparql;
    $this->routeMatch = $route_match;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('sparql.endpoint'),
      $container->get('current_route_match'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    // We're doing a direct SPARQL query as is hard to group on a multi-value
    // field using the SPARQL entity query.
    $results = $this->sparql->query('SELECT DISTINCT(?category)
      WHERE {
        ?solution_id a <http://www.w3.org/ns/dcat#Dataset> .
        ?solution_id <http://joinup.eu/eif/categories> ?category .
      }');

    $categories = array_map(function (\stdClass $row): string {
      return $row->category->getValue();
    }, $results->getArrayCopy());

    $active_category = $this->routeMatch->getParameter('eif_category');
    $category_links = [];
    foreach ($this->entityTypeManager->getStorage('taxonomy_term')->loadMultiple($categories) as $term) {
      $encoded_category = strtolower(str_replace(' ', '-', $term->label()));
      $category_link = [
        '#type' => 'link',
        '#title' => $term->label(),
        '#url' => Url::fromRoute('eif.solutions', [
          'node' => EifInterface::EIF_SOLUTIONS_NID,
          'eif_category' => $encoded_category,
        ]),
      ];

      if (!empty($active_category) && $active_category === $encoded_category) {
        $category_link['#attributes']['class'][] = 'is-active';
      }
      $category_links[] = $category_link;
    }

    $all_link = [
      '#type' => 'link',
      '#title' => $this->t('All'),
      '#url' => Url::fromRoute('entity.node.canonical', [
        'node' => EifInterface::EIF_SOLUTIONS_NID,
      ]),
    ];
    if (empty($active_category)) {
      $all_link['#attributes']['class'][] = 'is-active';
    }

    return [
      [
        '#theme' => 'eif_category_navigator',
        '#all_link' => $all_link,
        '#category_links' => $category_links,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return Cache::mergeTags(parent::getCacheContexts(), ['url.path']);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return Cache::mergeTags(parent::getCacheTags(), [
      'rdf_entity_list:solution',
    ]);
  }

}
