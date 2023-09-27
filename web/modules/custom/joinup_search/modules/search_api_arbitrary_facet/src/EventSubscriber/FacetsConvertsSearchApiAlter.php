<?php

declare(strict_types = 1);

namespace Drupal\joinup_search\EventSubscriber;

use Drupal\facets\FacetInterface;
use Drupal\search_api_solr\Event\PreQueryEvent;
use Drupal\search_api_solr\Event\SearchApiSolrEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Facets search subscriber.
 */
class FacetsConvertsSearchApiAlter implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      SearchApiSolrEvents::PRE_QUERY => 'preQuery',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function preQuery(PreQueryEvent $event): void {
    $query = $event->getSearchApiQuery();

    /** @var \Drupal\search_api_arbitrary_facet\Plugin\ArbitraryFacetManager $arbitrary_facet_manager */
    $arbitrary_facet_manager = \Drupal::getContainer()
      ->get('plugin.manager.arbitrary_facet');

    /** @var \Solarium\QueryType\Select\Query\Query $solarium_query */
    $facet_set = $solarium_query->getFacetSet();

    foreach ($query->getOption('search_api_arbitrary_facet', []) as $facet_name => $options) {
      // Don't overwrite facets that already exist. The latest version of
      // Solarium throws an exception if we do this.
      // @todo This hook is being called twice by SearchApiSolrBackend::search().
      //   Replace it by either PreQueryEvent or PostConvertedQueryEvent.
      //   Then we no longer need this workaround.
      if ($facet_set->getFacet($facet_name) instanceof FacetInterface) {
        continue;
      }
      $facet = $facet_set->createFacetMultiQuery($facet_name);

      /** @var \Drupal\search_api_arbitrary_facet\Plugin\ArbitraryFacetInterface $plugin_id */
      $plugin = $arbitrary_facet_manager->createInstance($options['arbitrary_facet_plugin']);
      $definition = $plugin->getFacetDefinition();
      $solr_field_names = search_api_arbitrary_facet_search_fieldnames_from_index($query->getIndex());

      // For "OR" facets, add the expected tag for exclusion.
      $excludes = isset($options['operator']) && strtolower($options['operator']) === 'or' ? ["arbitrary:$facet_name"] : [];

      foreach ($definition as $facet_identifier => $facet_attribute) {
        // Translate the field name to the index field name.
        $solr_field_name = $solr_field_names[$facet_attribute['field_name']];
        $filter = $this->searchApiArbitraryFacetCreateFilterQuery($solr_field_name, $facet_attribute['field_condition'], $facet_attribute['field_operator'] ?? NULL);
        $facet->createQuery($facet_identifier, $filter, $excludes);
      }
    }
  }

  /**
   * Converts condition data into a normal query.
   *
   * Mimics the solr's filter creation. Formatting values is not supported yet.
   *
   * @param string $field
   *   The solr field.
   * @param mixed $value
   *   The value.
   * @param string $operator
   *   The operator.
   *
   * @return string
   *   A solr query string.
   *
   * @see \Drupal\search_api_solr\Plugin\search_api\backend\SearchApiSolrBackend::createFilterQuery
   */
  private function searchApiArbitraryFacetCreateFilterQuery(string $field, mixed $value, string $operator = '='): string {
    if (!is_array($value)) {
      $value = [$value];
    }

    if (1 == count($value)) {
      $value = array_shift($value);

      switch ($operator) {
        case 'IN':
          $operator = '=';
          break;

        case 'NOT IN':
          $operator = '<>';
          break;
      }
    }

    switch ($operator) {
      case '<>':
        if (is_null($value)) {
          return "$field:[* TO *]";
        }
        else {
          return "(*:* -$field:$value)";
        }

      case '<':
        return "$field:{* TO $value}";

      case '<=':
        return "$field:[* TO $value]";

      case '>=':
        return "$field:[$value TO *]";

      case '>':
        return "$field:{{$value} TO *}";

      case 'BETWEEN':
        return "$field:[" . array_shift($value) . ' TO ' . array_shift($value) . ']';

      case 'NOT BETWEEN':
        return "(*:* -$field:[" . array_shift($value) . ' TO ' . array_shift($value) . '])';

      case 'IN':
        $parts = [];
        $null = FALSE;
        foreach ($value as $v) {
          if (is_null($v)) {
            $null = TRUE;
          }
          else {
            $parts[] = "$field:$v";
          }
        }
        if ($null) {
          // @see https://stackoverflow.com/questions/4238609/how-to-query-solr-for-empty-fields/28859224#28859224
          return "(*:* -$field:[* TO *])";
        }
        return '(' . implode(" ", $parts) . ')';

      case 'NOT IN':
        $parts = [];
        $null = FALSE;
        foreach ($value as $v) {
          if (is_null($v)) {
            $null = TRUE;
          }
          else {
            $parts[] = "-$field:$v";
          }
        }
        return '(' . ($null ? "$field:[* TO *]" : '*:*') . ' ' . implode(" ", $parts) . ')';

      case '=':
      default:
        if (is_null($value)) {
          // @see https://stackoverflow.com/questions/4238609/how-to-query-solr-for-empty-fields/28859224#28859224
          return "(*:* -$field:[* TO *])";
        }
        else {
          return "$field:$value";
        }
    }
  }

}
