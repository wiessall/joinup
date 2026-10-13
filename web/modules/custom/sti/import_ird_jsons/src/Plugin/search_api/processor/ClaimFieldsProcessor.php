<?php

namespace Drupal\import_ird_jsons\Plugin\search_api\processor;

use Drupal\node\NodeInterface;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;

/**
 * Indexes claim data as two packed multi-value fields.
 *
 * Instead of one Solr field per (collection, property) pair — which created
 * 1,477+ field definitions and caused 237k createField() calls per page
 * load — this processor packs all claim data into two fields:
 *
 *   claim_facet:  "collection::property::value" triples for faceting
 *   claim_key:    "collection::property" pairs for discovering which
 *                 properties a collection uses
 *
 * The "::" delimiter is safe because collection slugs and property slugs
 * are already normalized to [a-z0-9_].
 *
 * Faceting works because AdjustGroupContentSubscriber scopes the Solr
 * query to entity_groups IN [collection_nid] before facet computation.
 * Solr only sees documents belonging to that collection, so facet counts
 * on claim_facet naturally return only that collection's values.
 *
 * A custom Facets widget (GroupedClaimDropdownWidget) splits the
 * concatenated results into per-property dropdowns at render time.
 *
 * @SearchApiProcessor(
 *   id = "claim_fields",
 *   label = @Translation("Claim fields"),
 *   description = @Translation("Indexes claim data as packed facet triples."),
 *   stages = {
 *     "add_properties" = 0,
 *     "preprocess_index" = 0,
 *   },
 * )
 */
class ClaimFieldsProcessor extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   *
   * Three fields total, regardless of how many collections or properties exist.
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL): array {
    if ($datasource !== NULL) {
      return [];
    }

    return [
      'claim_facet' => new ClaimListProperty([
        'label'        => 'Claim facet values',
        'description'  => 'Concatenated collection::property::value triples for faceting.',
        'processor_id' => $this->getPluginId(),
      ]),
      'claim_key' => new ClaimListProperty([
        'label'        => 'Claim property keys',
        'description'  => 'Concatenated collection::property identifiers.',
        'processor_id' => $this->getPluginId(),
      ]),
      'claim_collection' => new ClaimListProperty([
        'label'        => 'Claim collection',
        'description'  => 'Collection slugs asserting claims on this solution.',
        'processor_id' => $this->getPluginId(),
      ]),
    ];
  }

  /**
   * {@inheritdoc}
   *
   * Reads claim paragraphs and packs values into the three fields.
   */
  public function addFieldValues(ItemInterface $item): void {
    $node = $item->getOriginalObject()->getValue();
    if (!$node instanceof NodeInterface || !$node->hasField('field_claims')) {
      return;
    }

    $facet_values = [];
    $key_values   = [];
    $collections  = [];

    foreach ($node->get('field_claims')->referencedEntities() as $paragraph) {
      $property_entity = $paragraph->get('field_claim_property')->entity;
      if (!$property_entity) {
        continue;
      }

      $collection_node = $paragraph->get('field_claimed_by')->entity;
      if (!$collection_node) {
        continue;
      }
      $coll_slug = $collection_node->get('short_id')->value ?? NULL;
      if (!$coll_slug) {
        continue;
      }

      $coll_slug = $this->slug($coll_slug);
      $prop_slug = $this->slug($property_entity->label());
      $collections[] = $coll_slug;
      $key_values[]  = $coll_slug . '::' . $prop_slug;

      // Collect values from both reference and literal fields.
      if ($ref = $paragraph->get('field_claim_ref_value')->entity) {
        $facet_values[] = $coll_slug . '::' . $prop_slug . '::' . $ref->label();
      }
      // if ($literal = $paragraph->get('field_claim_literal_value')->value ?? NULL) {
        // $facet_values[] = $coll_slug . '::' . $prop_slug . '::' . $literal;
      // }
    }

    foreach ($item->getFields() as $field) {
      match ($field->getPropertyPath()) {
        'claim_facet'      => $field->setValues(array_values(array_unique($facet_values))),
        'claim_key'        => $field->setValues(array_values(array_unique($key_values))),
        'claim_collection' => $field->setValues(array_values(array_unique($collections))),
        default            => NULL,
      };
    }
  }

  private function slug(string $value): string {
    $s = strtolower($value);
    $s = preg_replace('/[^a-z0-9]+/', '_', $s);
    return trim($s, '_');
  }

}
