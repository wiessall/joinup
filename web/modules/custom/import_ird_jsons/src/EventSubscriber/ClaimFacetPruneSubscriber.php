<?php

declare(strict_types=1);

namespace Drupal\import_ird_jsons\EventSubscriber;

use Drupal\search_api\Event\QueryPreExecuteEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Prunes legacy per-collection claim facets from the query.
 *
 * HISTORY / WHY THE GUARD BELOW EXISTS
 * ------------------------------------
 * The original design had ClaimFieldsProcessor create one Solr field per
 * (collection, property) pair, named claim_{slug}_{property}. Every collection
 * page then asked Solr to compute counts for ALL of them across ALL
 * collections — 1,477+ facets, nearly all returning zero. This subscriber
 * stripped the ones belonging to other collections.
 *
 * The current design replaced that with three generic fields:
 *
 *   claim_facet       "collection::property::value" triples
 *   claim_key         "collection::property" pairs
 *   claim_collection  bare collection id
 *
 * Scoping now happens inside the VALUES, and the query is already restricted
 * to the current collection's solutions, so a facet on claim_facet naturally
 * returns only that collection's triples. No pruning is required.
 *
 * The old prefix test, however, silently removed them: "claim_facet" starts
 * with "claim_" (so it was treated as a claim facet) but not with
 * "claim_{slug}_" (so it was unset). The result was that claim_facet was
 * stripped from every query, Solr never computed it, the facet returned zero
 * results, and GroupedClaimDropdownWidget rendered an empty container.
 *
 * GENERIC_FIELDS below is the fix: these are never pruned. The legacy
 * prefix logic is retained only so that any surviving claim_{slug}_{property}
 * fields still behave as before. Once no legacy fields remain, this whole
 * subscriber can be deleted along with its entry in
 * import_ird_jsons.services.yml.
 */
class ClaimFacetPruneSubscriber implements EventSubscriberInterface {

  /**
   * Generic claim fields produced by the current ClaimFieldsProcessor.
   *
   * These are collection-agnostic by design and must never be pruned.
   */
  private const GENERIC_FIELDS = [
    'claim_facet',
    'claim_key',
    'claim_collection',
    'custom_value',
  ];

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      SearchApiEvents::QUERY_PRE_EXECUTE . '.search_api_field_listing_content' => [
        ['pruneClaimFacets', -10],
      ],
    ];
  }

  /**
   * Removes legacy per-collection claim facets from the query.
   */
  public function pruneClaimFacets(QueryPreExecuteEvent $event): void {
    $query = $event->getQuery();
    $facets = $query->getOption('search_api_facets');
    if (empty($facets)) {
      return;
    }

    /** @var \Drupal\Core\Entity\EntityInterface|null $entity */
    $entity = $query->getOption('search_api_field entity');
    if (!$entity || !$entity->hasField('short_id')) {
      return;
    }

    $slug = $entity->get('short_id')->value;
    if (!$slug) {
      return;
    }

    $prefix = 'claim_' . $this->slug($slug) . '_';

    foreach (array_keys($facets) as $facet_id) {
      // Generic claim fields are collection-agnostic — always keep them.
      if (in_array($facet_id, self::GENERIC_FIELDS, TRUE)) {
        continue;
      }
      // Non-claim facets (collection_type, etc.) are never touched.
      if (!str_starts_with($facet_id, 'claim_')) {
        continue;
      }
      // Legacy claim facets belonging to other collections are removed.
      if (!str_starts_with($facet_id, $prefix)) {
        unset($facets[$facet_id]);
      }
    }

    $query->setOption('search_api_facets', $facets);
  }

  /**
   * Normalize a string to a Solr-safe slug.
   *
   * Must match ClaimFieldsProcessor::slug() exactly.
   */
  private function slug(string $value): string {
    $s = strtolower($value);
    $s = preg_replace('/[^a-z0-9]+/', '_', $s);
    return trim($s, '_');
  }

}