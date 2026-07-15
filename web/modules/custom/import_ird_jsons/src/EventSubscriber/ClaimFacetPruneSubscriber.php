<?php

declare(strict_types=1);

namespace Drupal\import_ird_jsons\EventSubscriber;

use Drupal\search_api\Event\QueryPreExecuteEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Prunes claim facets that don't belong to the current collection.
 *
 * Runs on the listing_content query AFTER the facet manager has added
 * all 1,477 facets, but BEFORE the query hits Solr. Keeps only facets
 * whose field_identifier starts with claim_{current_collection_slug}_.
 * Non-claim facets (like collection_type) are always kept.
 */
class ClaimFacetPruneSubscriber implements EventSubscriberInterface {

  public static function getSubscribedEvents(): array {
    // Listen to the listing_content-specific event.
    // Priority -10: runs AFTER SearchApiSubscriber (which adds facets)
    // but BEFORE the query executes.
    return [
      SearchApiEvents::QUERY_PRE_EXECUTE . '.search_api_field_listing_content' => [
        ['pruneClaimFacets', -10],
      ],
    ];
  }

  public function pruneClaimFacets(QueryPreExecuteEvent $event): void {
    $query = $event->getQuery();
    $facets = $query->getOption('search_api_facets');
    if (empty($facets)) {
      return;
    }
    $before = count($query->getOption('search_api_facets') ?? []);


    $entity = $query->getOption('search_api_field entity');
    if (!$entity || !$entity->hasField('short_id')) {
      return;
    }

    $slug = $entity->get('short_id')->value;
    if (!$slug) {
      return;
    }

    // Normalize slug the same way ClaimFieldsProcessor::slug() does.
    $slug = trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($slug)), '_');
    $prefix = 'claim_' . $slug . '_';

    foreach (array_keys($facets) as $facet_id) {
      // Keep non-claim facets (collection_type, etc.) untouched.
      if (!str_starts_with($facet_id, 'claim_')) {
        continue;
      }
      // Remove claim facets that belong to other collections.
      if (!str_starts_with($facet_id, $prefix)) {
        unset($facets[$facet_id]);
      }
    }

    $query->setOption('search_api_facets', $facets);
    $after = count($facets);
    \Drupal::logger('php')->notice(
        "Pruned claim facets: @before → @after for collection @slug", 
        ['@before' => $before, '@after' => $after, '@slug' => $slug]
    );
  }

}