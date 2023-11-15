<?php

declare(strict_types = 1);

namespace Drupal\eira\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\sparql_entity_storage\Driver\Database\sparql\ConnectionInterface;
use Drupal\taxonomy\TermInterface;
use EasyRdf\RdfNamespace;
use EasyRdf\Resource;
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

  /**
   * Constructs a new EiraPropertiesBlock instance.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $pluginId
   *   The plugin_id for the plugin instance.
   * @param mixed $pluginDefinition
   *   The plugin implementation definition.
   * @param \Drupal\sparql_entity_storage\Driver\Database\sparql\ConnectionInterface $sparql
   *   The sparql endpoint service.
   * @param \Drupal\Core\Routing\CurrentRouteMatch $currentRouteMatch
   *   The current_route_match service.
   */
  public function __construct(array $configuration, $pluginId, $pluginDefinition, protected ConnectionInterface $sparql, protected CurrentRouteMatch $currentRouteMatch) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $pluginId, $pluginDefinition): EiraPropertiesBlock {
    return new static(
      $configuration,
      $pluginId,
      $pluginDefinition,
      $container->get('sparql.endpoint'),
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

    $this->setupNamespaces();
    [$rows, $namespaces] = $this->getRows($term->id());

    return [
      '#type' => 'container',
      'namespaces_table' => [
        '#theme' => 'table',
        '#header' => [
          'namespace' => $this->t('Namespace'),
          'uri' => $this->t('URI'),
        ],
        '#rows' => array_values($namespaces),
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
  }

  /**
   * Generates the row for the term with the provided ID.
   *
   * @param string $tid
   *   The term ID.
   *
   * @return array
   *   The row data.
   */
  protected function getRows(string $tid): array {
    $query = <<<SPARQL
PREFIX skos: <http://www.w3.org/2004/02/skos/core#>
PREFIX eira: <http://data.europa.eu/dr8/>
WITH <http://eira_skos_properties>
SELECT ?property ?value
WHERE {
  ?s ?property ?value .
  FILTER EXISTS { ?s skos:related <$tid> }
}
SPARQL;

    $results = $this->sparql->query($query);
    $rows = [];
    $namespaces = [];
    foreach ($results as $result) {
      $property = $result->property->getUri();
      // Do not show the skos:related property.
      if ($property === 'http://www.w3.org/2004/02/skos/core#related') {
        continue;
      }

      $value = $result->value instanceof Resource ? $result->value->getUri() : $result->value->getValue();
      // Do not show empty values.
      if (empty($value)) {
        continue;
      }

      $shortenedProperty = RdfNamespace::shorten($property);
      $prefix = RdfNamespace::prefixOfUri($property);
      $namespaces[$prefix] = [
        'namespace' => $prefix . ':',
        'uri' => RdfNamespace::get($prefix),
      ];

      $rows[] = [
        'property' => $shortenedProperty,
        'value' => $value,
      ];
    }
    return [$rows, $namespaces];
  }

  /**
   * Sets up the namespaces.
   *
   * According to the requirements, the EIRA team needs some specific changes
   * in the presented namespaces. Alter the data for this block to match the
   * requirements.
   */
  protected function setupNamespaces(): void {
    RdfNamespace::set('eira', 'http://data.europa.eu/dr8/');
    // Replace the `dc` namespace with `dct` - requested by the client.
    RdfNamespace::delete('dc');
    RdfNamespace::delete('dcterms');
    RdfNamespace::set('dct', 'http://purl.org/dc/terms/');
    RdfNamespace::set('owlturtle', 'http://www.semanticweb.org/owl/owlapi/turtle#');
  }

}
