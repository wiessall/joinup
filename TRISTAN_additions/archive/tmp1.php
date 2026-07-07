<?php

const USED = [
  'title',
  'field_original_internal_id',
  'field_long_description',
  'field_information_resource_date',
  'field_link',
  'field_languages',
  'field_innovation_domain',
  'field_use_cases',
  'field_owner',
  'field_data_source',
];

const ORG_USED = [
  'title',
  'body',
  'field_acronym',
  'field_country',
  'field_data_source',
  'field_link',
  'field_organization_type',
  'field_region',
];

function build_org_record(\Drupal\node\NodeInterface $org): array {
  $annotations = [];
  foreach ($org->getFields() as $name => $field) {
    if (in_array($name, ORG_USED, true)) continue;
    if (!($field->getFieldDefinition() instanceof \Drupal\field\FieldConfigInterface)) continue;
    if ($field->isEmpty()) continue;
    $def   = $field->getFieldDefinition();
    $type  = $def->getType();
    $multi = $def->getFieldStorageDefinition()->getCardinality() !== 1;
    $vals  = [];
    foreach ($field as $item) {
      $vals[] = match ($type) {
        'entity_reference'  => $item->entity?->label(),
        'link'              => $item->uri,
        'boolean'           => (bool) $item->value,
        'text_with_summary' => $item->value,
        default             => $item->value,
      };
    }
    $vals = array_values(array_filter($vals, fn($v) => $v !== null && $v !== ''));
    $annotations[$name] = $multi ? $vals : ($vals[0] ?? null);
  }

  return [
    'drupal_nid'  => $org->id(),
    'id'          => 'org_' . $org->id(),
    'title'       => $org->label(),
    'description' => $org->get('body')->value,
    'acronym'     => $org->get('field_acronym')->value,
    'country'     => $org->get('field_country')->entity?->label(),
    'region'      => $org->get('field_region')->entity?->label(),
    'link'        => $org->get('field_link')->uri,
    'owner_type'  => array_map(
      fn($t) => $t->label(),
      $org->get('field_organization_type')->referencedEntities()
    ),
    '_annotations' => $annotations,
  ];
}

function extract_value($item_list) {
  $def = $item_list->getFieldDefinition();
  $type = $def->getType();
  $multi = $def->getFieldStorageDefinition()->getCardinality() !== 1;

  $values = [];
  foreach ($item_list as $item) {
    $values[] = match ($type) {
      'entity_reference' => $item->entity?->label(),
      'link'             => $item->uri,
      default            => $item->value,
    };
  }
  $values = array_values(array_filter($values, fn($v) => $v !== null && $v !== ''));

  return $multi ? $values : ($values[0] ?? null);
}

$storage = \Drupal::entityTypeManager()->getStorage('node');
$total = $storage->getQuery()
  ->condition('type', 'source_record')
  ->accessCheck(FALSE)
  ->count()
  ->execute();
print "Total: $total\n";

$by_collection = [];
$orgs_by_collection = [];

$batch_size = 50000;

for ($offset = 0; $offset < $total; $offset += $batch_size) {
  $nids = $storage->getQuery()
    ->condition('type', 'source_record')
    ->accessCheck(FALSE)
    ->sort('nid')
    ->range($offset, $batch_size)
    ->execute();
  $nodes = $storage->loadMultiple($nids);

  foreach ($nodes as $n) {
    $annotations = [];
    foreach ($n->getFields() as $name => $field) {
      if (in_array($name, USED, true)) continue;
      if (!($field->getFieldDefinition() instanceof \Drupal\field\FieldConfigInterface)) continue;
      if ($field->isEmpty()) continue;
      $annotations[$name] = extract_value($field);
    }

    $collections = array_map(
      fn($t) => $t->label(),
      $n->get('field_data_source')->referencedEntities()
    );
    if (empty($collections)) {
      $collections = ['_uncategorized'];
    }

    $record = [
      'id'             => $n->get('field_original_internal_id')->value ?: 'sr_' . $n->id(),
      'title'          => $n->label(),
      'description'    => $n->get('field_long_description')->value,
      'date'           => $n->get('field_information_resource_date')->value,
      'url'            => $n->get('field_link')->uri,
      'language_names' => array_map(fn($t) => $t->label(), $n->get('field_languages')->referencedEntities()),
      'domain_names'   => array_map(fn($t) => $t->label(), $n->get('field_innovation_domain')->referencedEntities()),
      'topic_names'    => array_map(fn($t) => $t->label(), $n->get('field_use_cases')->referencedEntities()),
      'owner_name'     => $n->get('field_owner')->entity?->label(),
      '_annotations'   => $annotations,
    ];

    $owner = $n->get('field_owner')->entity;
    if ($owner instanceof \Drupal\node\NodeInterface) {
      foreach ($collections as $collection) {
        // ??= means we build the record once per org per collection
        $orgs_by_collection[$collection][$owner->id()] ??= build_org_record($owner);
      }
    }

    foreach ($collections as $collection) {
      $by_collection[$collection][] = $record;
    }
  }

  // Free memory between batches — referenced entities cache aggressively
  $storage->resetCache($nids);
  print "Processed " . min($offset + $batch_size, $total) . " / $total\n";
}

// Write one file per collection, AFTER the loop
foreach ($by_collection as $collection => $records) {
  $slug = preg_replace('/[^a-z0-9]+/', '_', strtolower($collection));
  $slug = trim($slug, '_') ?: 'unnamed';
  $path = "/tmp/source_records_{$slug}.json";
  file_put_contents($path, json_encode([
    'meta' => [
      'collection_label' => $collection,
      'record_count'     => count($records),
      'export_timestamp' => date('c'),
    ],
    'records' => $records,
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
  print "Wrote " . count($records) . " records → $path\n";
}

foreach ($orgs_by_collection as $collection => $orgs) {
  $slug = preg_replace('/[^a-z0-9]+/', '_', strtolower($collection));
  $slug = trim($slug, '_') ?: 'unnamed';
  $path = "/tmp/organizations_{$slug}.json";
  file_put_contents($path, json_encode([
    'meta' => [
      'collection_label' => $collection,
      'record_count'     => count($orgs),
      'export_timestamp' => date('c'),
    ],
    'records' => array_values($orgs),
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
  print "Wrote " . count($orgs) . " orgs → $path\n";
}
