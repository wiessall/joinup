<?php

declare(strict_types=1);

namespace Drupal\joinup_search\EventSubscriber;

use Drupal\search_api_solr\Event\PostCreateIndexDocumentsEvent;
use Drupal\search_api_solr\Event\PostFieldMappingEvent;
use Drupal\search_api_solr\Event\PreQueryEvent;
use Drupal\search_api_solr\Event\SearchApiSolrEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Apply the global settings to all search_api_field solarium queries on Portal.
 */
class JoinupGlobalSearchApiAlter implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      SearchApiSolrEvents::PRE_QUERY => 'preQuery',
      SearchApiSolrEvents::POST_FIELD_MAPPING => 'postFieldMapping',
      SearchApiSolrEvents::POST_CREATE_INDEX_DOCUMENTS => 'postCreateIndexDocument',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function preQuery(PreQueryEvent $event): void {
    $query = $event->getSearchApiQuery();
    $solarium_query = $event->getSolariumQuery();

    /** @var \Drupal\search_api_field\Plugin\Field\FieldType\SearchItem $item */
    if (!$item = $query->getOption('search_api_field item')) {
      return;
    }

    // Keep the edismax parser to allow document boosting to work properly.
    // @todo remove when document boosting is reimplemented by search_api_solr
    // @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-4907
    $solarium_query->addParam('defType', 'edismax');

    $value = $item->getValue()['value'];
    if (empty($value['query_builder']['filters'])) {
      return;
    }

    // Extract all the plugins used.
    $plugin_ids = array_column($value['query_builder']['filters'], 'plugin');
    // If any plugin other than the direct entity reference ones are used,
    // bail out.
    $other_plugins = array_filter($plugin_ids, function ($plugin_id) {
      return strpos($plugin_id, 'bundle_autocomplete:') !== 0;
    });
    if (!empty($other_plugins)) {
      return;
    }

    // Get the real names used to index on Solr.
    /** @var \Drupal\search_api_solr\Plugin\search_api\backend\SearchApiSolrBackend $solr_backend */
    $solr_backend = $query->getIndex()->getServerInstance()->getBackend();
    $solr_fields = $solr_backend->getSolrFieldNames($query->getIndex());

    // Build a sorting function that attributes a weight following the order of
    // the elements in the UI.
    $plugins = array_reverse($value['query_builder']['filters'], TRUE);
    $sort = '';
    foreach ($plugins as $delta => $plugin_config) {
      $field = $plugin_config['field'];
      if (!isset($solr_fields[$field]) || empty($plugin_config['target_id'])) {
        continue;
      }

      if (empty($sort)) {
        $sort = $delta;
        continue;
      }

      $sort_value = is_numeric($plugin_config['target_id']) ?
        $plugin_config['target_id'] :
        $solarium_query->getHelper()
          ->escapePhrase((string) $plugin_config['target_id']);

      $sort = sprintf(
        'if(eq(%s,%s),%s,%s)',
        $solr_fields[$field],
        $sort_value,
        $delta,
        $sort
      );
    }

    $sorts = $solarium_query->getSorts();
    $solarium_query->setSorts([$sort => 'asc'] + $sorts);
  }

  /**
   * {@inheritdoc}
   */
  public function postFieldMapping(PostFieldMappingEvent $event): void {
    // Single value fields need to be used for sorting.
    $fields = $event->getFieldMapping();
    $mappings = [
      'entity_created' => 'ds_entity_created',
      'published_on' => 'ds_published_on',
    ];

    foreach ($mappings as $alias => $solr_field) {
      if (isset($fields[$alias])) {
        $fields[$alias] = $solr_field;
      }
    }
    $event->setFieldMapping($fields);
  }

  /**
   * {@inheritdoc}
   */
  public function postCreateIndexDocument(PostCreateIndexDocumentsEvent $event): void {
    // By default, solr server configuration has a property named
    // sortMissingLast set to true for all fields defined. This means
    // that two different entity types, one with a property set to false
    // and one without the property, will result in having the one
    // without the property placed last.
    // Set the "sticky" property on all documents that are missing it. The
    // property is prefixed with "bs_" because "b" stands for boolean
    // and "s" for single value, as they are used for sorting.
    // Set the "state" property on all documents that are missing it and are
    // stored in the unpublished index. The property is prefixed with "ss_"
    // because the first "s" stands for 'string' and the second one stands
    // for single value.
    $documents = $event->getSolariumDocuments();
    foreach ($documents as $document) {
      if (empty($document->getFields()['bs_sticky'])) {
        $document->setField('bs_sticky', FALSE);
      }

      if (empty($document->getFields()['sm_entity_status']) && $document->getFields()['index_id'] === 'unpublished') {
        $document->setField('sm_entity_status', 'unpublished');
      }
      if (empty($document->getFields()['sm_entity_status']) && $document->getFields()['index_id'] === 'published') {
        // Because of the way the search API works, when we
        // perform a search, it only returns items *with* a value for the
        // conditions. Thus, when we search for an item that is not archived, if
        // items do not have a value for the state field, they will not be
        // included in the results. This is why we set the value to a special
        // value that will never be used for the state field.
        $document->setField('sm_entity_status', 'published');
      }
    }
    $event->setSolariumDocuments($documents);
  }

}
