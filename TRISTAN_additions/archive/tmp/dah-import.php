<?php
/**
 * DAH solution import — drush eval script.
 * Usage: ddev drush scr scripts/dah-import.php -- /path/to/solutions.json
 */

// Config — adjust if IDs differ
$config = [
  'json_path'         => $extra[0] ?? '/var/www/html/dah-solutions.json',
  'collection_id'     => 13,   // Digital Agri Hub collection node id → og_audience
  'default_classification' => 955,
  'default_contact'   => 17,
  'default_domain'    => 958,
  'default_topic'     => 843,
  'default_language'  => 869,
  'default_state'     => 'published',
  'limit'             => 5,    // Start small. Set to NULL for all.
];

$data = json_decode(file_get_contents($config['json_path']), TRUE);
if (!isset($data['solutions'])) {
  print "ERROR: no 'solutions' key in JSON\n";
  return;
}

$solutions = $config['limit']
  ? array_slice($data['solutions'], 0, $config['limit'])
  : $data['solutions'];

$created = 0;
$skipped = 0;
$owner_cache = [];

foreach ($solutions as $s) {
  // Skip if already imported (match by DAH id stored as short_id — adjust if you use a different field)
  $existing = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
    'type' => 'solution',
    'title' => $s['name'],
  ]);
  if ($existing) {
    print "SKIP: " . $s['name'] . " (already exists)\n";
    $skipped++;
    continue;
  }

  // --- Owner ---
  $org_name = $s['organisation']['name'] ?? $s['organisationname'] ?? 'Unknown';
  if (!isset($owner_cache[$org_name])) {
    $owner_nodes = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
      'type' => 'owner',
      'title' => $org_name,
    ]);
    if ($owner_nodes) {
      $owner_cache[$org_name] = reset($owner_nodes);
    } else {
      $owner = \Drupal\node\Entity\Node::create([
        'type'   => 'owner',
        'title'  => $org_name,
        'status' => 1,
      ]);
      $owner->save();
      $owner_cache[$org_name] = $owner;
      print "  + Owner created: $org_name (nid " . $owner->id() . ")\n";
    }
  }
  $owner = $owner_cache[$org_name];

  // --- Solution ---
  $abstract = strip_tags($s['descriptionshort'] ?? $s['description'] ?? $s['name']);
  $abstract = substr(trim($abstract), 0, 250);

  $node = \Drupal\node\Entity\Node::create([
    'type'            => 'solution',
    'title'           => $s['name'],
    'status'          => 1,
    'abstract'        => $abstract,
    'description'     => [
      'value' => $s['description'] ?? '',
      'format' => 'content_editor',
    ],
    'landing_page'    => $s['url'] ?? NULL,
    'classification'  => $config['default_classification'],
    'contact'         => $config['default_contact'],
    'domain'          => $config['default_domain'],
    'field_topic'     => $config['default_topic'],
    'language'        => $config['default_language'],
    'field_state'     => $config['default_state'],
    'og_audience'     => $config['collection_id'],
    'owner'           => $owner->id(),
  ]);

  try {
    $node->save();
    print "OK: " . $s['name'] . " (nid " . $node->id() . ")\n";
    $created++;
  } catch (\Exception $e) {
    print "FAIL: " . $s['name'] . " — " . $e->getMessage() . "\n";
  }
}

print "\n=== Done: $created created, $skipped skipped ===\n";
