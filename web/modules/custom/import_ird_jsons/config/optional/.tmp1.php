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
];

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

$batch_size = 500;
for ($offset = 0; $offset < $total; $offset += $batch_size) {
  $nids = $storage->getQuery()
    ->condition('type', 'source_record')
    ->accessCheck(FALSE)
    ->sort('nid')
    ->range($offset, $batch_size)
    ->execute();
  $nodes = $storage->loadMultiple($nids);

  $records = [];
  foreach ($nodes as $n) {
    $annotations = [];
    foreach ($n->getFields() as $name => $field) {
      if (in_array($name, USED, true)) continue;
      if (!($field->getFieldDefinition() instanceof \Drupal\field\FieldConfigInterface)) continue;
      if ($field->isEmpty()) continue;
      $annotations[$name] = extract_value($field);
    }

    $records[] = [
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
  }

  $path = '/tmp/source_records_' . str_pad($offset, 6, '0', STR_PAD_LEFT) . '.json';
  file_put_contents($path, json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
  print "Exported $offset / $total → $path\n";
}