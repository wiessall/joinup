<?php

namespace Drupal\import_ird_jsons\Plugin\search_api\processor;

use Drupal\import_ird_jsons\Plugin\search_api\processor\ClaimListProperty;
use Drupal\node\NodeInterface;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;

/**
 * Derives one indexed field per (collection, claim property) pair.
 *
 * Level 2 semantics: two collections asserting a property with the same name
 * do NOT share a field. `stf/coolness` and `whh/coolness` are separate index
 * fields, separate facets, separate buckets. Cross-collection equivalence is
 * expressed later via SKOS mappings, not by silent collapsing here.
 *
 * Field naming: claim_{collection_slug}_{property_slug}
 * Also emits claim_collection (multi-value) for scoping the central view.
 *
 * @SearchApiProcessor(
 *   id = "claim_fields",
 *   label = @Translation("Claim fields"),
 *   description = @Translation("Adds one field per (collection, claim property) pair to solution nodes."),
 *   stages = {
 *     "add_properties" = 0,
 *     "preprocess_index" = 0,
 *   },
 * )
 */
// class ClaimFieldsProcessor extends ProcessorPluginBase {

//   /**
//    * {@inheritdoc}
//    *
//    * One ProcessorProperty per (collection, property) pair.
//    */
//   public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL): array {
//     if ($datasource !== NULL) {
//       return [];
//     }

//     $properties = [];

//     foreach ($this->collectionScopedPropertyTerms() as $key => $label) {
//       $properties[$key] = new ClaimListProperty([
//         'label'        => $label,
//         'description'  => "Claim values for: $label",
//         'processor_id' => $this->getPluginId(),
//       ]);
//     }

//     $properties['claim_collection'] = new ClaimListProperty([
//       'label'        => 'Claim collection',
//       'description'  => 'Collections asserting claims on this solution.',
//       'processor_id' => $this->getPluginId(),
//     ]);

//     return $properties;
//   }

//   /**
//    * {@inheritdoc}
//    *
//    * Groups claim paragraph values by (collection_slug, property_label) and
//    * writes each group into its collection-scoped field.
//    */
//   public function addFieldValues(ItemInterface $item): void {
//     $node = $item->getOriginalObject()->getValue();
//     if (!$node instanceof NodeInterface || !$node->hasField('field_claims')) {
//       return;
//     }

//     $by_field    = [];
//     $collections = [];

//     foreach ($node->get('field_claims')->referencedEntities() as $paragraph) {
//       $property_entity = $paragraph->get('field_claim_property')->entity;
//       if (!$property_entity) {
//         continue;
//       }

//       // Resolve the collection this claim was made by.
//       $collection_node = $paragraph->get('field_claimed_by')->entity;
//       if (!$collection_node) {
//         continue;
//       }
//       $coll_slug = $collection_node->get('short_id')->value ?? NULL;
//       if (!$coll_slug) {
//         continue;
//       }
//       $collections[] = $coll_slug;

//       // Build the collection-scoped field key.
//       $key = $this->fieldKey($coll_slug, $property_entity->label());

//       if ($ref = $paragraph->get('field_claim_ref_value')->entity) {
//         $by_field[$key][] = $ref->label();
//       }
//       if ($literal = $paragraph->get('field_claim_literal_value')->value ?? NULL) {
//         $by_field[$key][] = $literal;
//       }
//     }

//     foreach ($item->getFields() as $field) {
//       $path = $field->getPropertyPath();
//       if (isset($by_field[$path])) {
//         $field->setValues(array_values(array_unique($by_field[$path])));
//       }
//       elseif ($path === 'claim_collection') {
//         $field->setValues(array_values(array_unique($collections)));
//       }
//     }
//   }

//   /**
//    * [field_key => human_label] for every (collection, property) pair
//    * currently present in the claim_properties vocabulary.
//    *
//    * Collection-root terms (no parent) are skipped; only their leaf property
//    * terms produce fields. Deduplication is by field_key, so two collections
//    * asserting the same property name produce two distinct entries.
//    */
//   private function collectionScopedPropertyTerms(): array {
//     static $cache;
//     if (isset($cache)) {
//       return $cache;
//     }
//     $cache = [];

//     $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
//     $terms   = $storage->loadByProperties(['vid' => 'claim_properties']);

//     foreach ($terms as $term) {
//       $parents = $storage->loadParents($term->id());
//       if (empty($parents)) {
//         // Collection-root term — the collection slug itself. Skip.
//         continue;
//       }
//       $collection_root = reset($parents);
//       $coll_slug = $collection_root->label();  // parent term name IS the collection_id
//       $key       = $this->fieldKey($coll_slug, $term->label());
//       $cache[$key] = $coll_slug . ' / ' . $term->label();
//     }

//     return $cache;
//   }

//   /**
//    * Build a collection-scoped Solr-safe field key.
//    *
//    * ("stf", "Digital Solution Type (Category & sub-category)")
//    *   → "claim_stf_digital_solution_type_category_sub_category"
//    *
//    * Length capped so slugs of long property labels don't exceed Solr's
//    * dynamic-field naming headroom.
//    */
//   private function fieldKey(string $collection_slug, string $property_label): string {
//     $coll = $this->slug($collection_slug);
//     $prop = $this->slug($property_label);
//     // Cap the combined slug so total length stays safe. 'claim_' + coll + '_' + prop.
//     $prop = substr($prop, 0, 200 - strlen($coll));
//     return 'claim_' . $coll . '_' . $prop;
//   }

//   private function slug(string $value): string {
//     $s = strtolower($value);
//     $s = preg_replace('/[^a-z0-9]+/', '_', $s);
//     return trim($s, '_');
//   }

// }
class ClaimFieldsProcessor extends ProcessorPluginBase {

  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL): array {
    if ($datasource !== NULL) {
      return [];
    }

    return [
      'claim_facet' => new ClaimListProperty([
        'label'        => 'Claim facet values',
        'description'  => 'Concatenated collection::property::value triples.',
        'processor_id' => $this->getPluginId(),
      ]),
      'claim_key' => new ClaimListProperty([
        'label'        => 'Claim property keys',
        'description'  => 'Concatenated collection::property identifiers.',
        'processor_id' => $this->getPluginId(),
      ]),
      'claim_collection' => new ClaimListProperty([
        'label'        => 'Claim collection',
        'description'  => 'Collections asserting claims on this solution.',
        'processor_id' => $this->getPluginId(),
      ]),
    ];
  }

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

      // Collect values from both ref and literal fields.
      $values = [];
      if ($ref = $paragraph->get('field_claim_ref_value')->entity) {
        $values[] = $ref->label();
      }
      if ($literal = $paragraph->get('field_claim_literal_value')->value ?? NULL) {
        $values[] = $literal;
      }

      foreach ($values as $val) {
        $facet_values[] = $coll_slug . '::' . $prop_slug . '::' . $val;
      }
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