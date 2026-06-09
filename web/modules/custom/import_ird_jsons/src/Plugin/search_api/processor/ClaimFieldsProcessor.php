<?php

namespace Drupal\import_ird_jsons\Plugin\search_api\processor;

use Drupal\import_ird_jsons\Plugin\search_api\processor\ClaimListProperty;
use Drupal\node\NodeInterface;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;

/**
 * Derives one indexed field per claim property from field_claims paragraphs.
 *
 * For each unique claim_properties term label (deduped across collections),
 * adds a multi-value string field to solution node index documents.
 * Categorical claims → term label. Literal claims → raw text.
 * Also adds claim_collection for per-collection view scoping.
 *
 * @SearchApiProcessor(
 *   id = "claim_fields",
 *   label = @Translation("Claim fields"),
 *   description = @Translation("Adds one field per claim property to solution nodes."),
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
   * Returns one ProcessorProperty per unique claim property label,
   * plus claim_collection for collection-scoped filtering.
   * Only registered on the index level (datasource = NULL).
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL): array {
    if ($datasource !== NULL) {
      return [];
    }

    $properties = [];

    foreach ($this->uniquePropertyTerms() as $key => $label) {
      $properties[$key] = new ClaimListProperty([
        'label'        => $label,
        'description'  => "Claim values for: $label",
        'processor_id' => $this->getPluginId(),
      ]);
    }

    $properties['claim_collection'] = new ClaimListProperty([
      'label'        => 'Claim collection',
      'description'  => 'Collections asserting claims on this solution.',
      'processor_id' => $this->getPluginId(),
    ]);

    return $properties;
  }

  /*collection_nid
   * {@inheritdoc}
   *
   * For each solution node, groups claim paragraph values by property label
   * and populates the matching processor fields on the index item.
   */
  public function addFieldValues(ItemInterface $item): void {
    $node = $item->getOriginalObject()->getValue();
    if (!$node instanceof NodeInterface || !$node->hasField('field_claims')) {
      return;
    }

    // Build property_key → [values] map from all attached claim paragraphs.
    $by_property  = [];
    $collections  = [];

    foreach ($node->get('field_claims')->referencedEntities() as $paragraph) {
      $property_entity = $paragraph->get('field_claim_property')->entity;
      if (!$property_entity) {
        continue;
      }
      $key = $this->labelToKey($property_entity->label());

      // Categorical value (taxonomy term reference).
      if ($ref = $paragraph->get('field_claim_ref_value')->entity) {
        $by_property[$key][] = $ref->label();
      }

      // Literal value (free text).
      if ($literal = $paragraph->get('field_claim_literal_value')->value ?? null) {
        $by_property[$key][] = $literal;
      }

      // Collection for scoping.
      if ($collection = $paragraph->get('field_claimed_by')->entity) {
        $collections[] = $collection->get('short_id')->value ?? $collection->label();
      }
    }

    // Write values into whichever processor fields the index has enabled.
    foreach ($item->getFields() as $field) {
      $path = $field->getPropertyPath();
      if (isset($by_property[$path])) {
        $field->setValues(array_values(array_unique($by_property[$path])));
      }
      elseif ($path === 'claim_collection') {
        $field->setValues(array_values(array_unique($collections)));
      }
    }
  }

  /**
   * Returns [machine_key => human_label] for all unique claim property labels.
   *
   * Deduplicates by label so "Digital Solution Type" appears once even if
   * ird:annotate created separate terms for dv_solutions and fao-dvi.
   */
  private function uniquePropertyTerms(): array {
    static $cache;
    if (isset($cache)) {
      return $cache;
    }

    $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $terms   = $storage->loadByProperties(['vid' => 'claim_properties']);

    $seen  = [];
    $cache = [];

    foreach ($terms as $term) {
      // Skip collection-root terms (no parent = collection slug term).
      if (empty($storage->loadParents($term->id()))) {
        continue;
      }
      $label = $term->label();
      if (isset($seen[$label])) {
        continue;
      }
      $seen[$label]           = TRUE;
      $cache[$this->labelToKey($label)] = $label;
    }

    return $cache;
  }

  /**
   * Converts a human label into a safe Search API / Solr field key.
   *
   * "Digital Solution Type (Category & sub-category)" → "claim_digital_solution_type"
   */
  private function labelToKey(string $label): string {
    $key = strtolower($label);
    $key = preg_replace('/[^a-z0-9]+/', '_', $key);
    $key = trim($key, '_');
    $key = substr($key, 0, 60); // leave headroom for Solr field naming
    return 'claim_' . $key;
  }

}