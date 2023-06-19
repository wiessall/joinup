<?php

declare(strict_types = 1);

namespace Drupal\eira\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\sparql_entity_storage\Driver\Database\sparql\ConnectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an eirapropertiesblock block.
 *
 * @Block(
 *   id = "eira_properties_block",
 *   admin_label = @Translation("EIRA properties block"),
 *   category = @Translation("EIRA")
 * )
 */
class EiraPropertiesBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a new EiraPropertiesBlock instance.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\sparql_entity_storage\Driver\Database\sparql\ConnectionInterface $sparql
   *   The sparql.endpoint service.
   * @param \Drupal\Core\Routing\CurrentRouteMatch $currentRouteMatch
   *   The current_route_match service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected ConnectionInterface $sparql, protected CurrentRouteMatch $currentRouteMatch) {
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
      $container->get('sparql.endpoint'),
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $term = $this->currentRouteMatch->getParameter('taxonomy_term');
    $tid = $term->id();

    $query = <<<SPARQL
PREFIX skos: <http://www.w3.org/2004/02/skos/core#>
WITH <http://eira_skos_properties>
SELECT ?p ?o
WHERE {
  ?s ?p ?o .
  FILTER EXISTS { ?s skos:related <$tid> }
}
SPARQL;

    $result = $this->sparql->query($query);

    // Build a table with all the properties and their respective values.
    $build = [
      '#type' => 'table',
      '#header' => [
        $this->t('Property'),
        $this->t('Value'),
      ],
    ];

    foreach ($result as $row) {
      $build[] = [
        'property' => [
          '#plain_text' => $row->p->getUri(),
        ],
        'value' => [
          '#plain_text' => reset($row->o),
        ],
      ];
    }

    return $build;
  }

}
