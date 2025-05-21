<?php

/**
 * @file
 * Deploy functions for Joinup.
 *
 * This should only contain update functions that rely on the Drupal API and
 * need to run _after_ the configuration is imported.
 *
 * This is applicable in most cases. However, in case the update code enables
 * some functionality required for configuration to be successfully imported, it
 * should instead be placed in joinup_core.post_update.php.
 */

declare(strict_types=1);

use Drupal\taxonomy\Entity\Term;

/**
 * Implements hook_deploy_NAME().
 */
function joinup_core_deploy_200500(array &$sandbox): string {
  if (empty($sandbox['data'])) {
    $sandbox['data'] = \Drupal::state()->get('joinup_core.keywords');
    $sandbox['count'] = count($sandbox['data']);
    $sandbox['current'] = 0;
    $sandbox['progress'] = 0;

    $sandbox['normalized'] = [];
  }

  $get_normalized_id = function (string $term_name, array &$normalized): int {
    $time = \Drupal::time()->getRequestTime();
    $term_name = strtolower($term_name);
    $term_name = preg_replace('/\s+/', ' ', $term_name);
    $term_name = trim($term_name);
    // Remove the trailing s to combine the singular and plural forms.
    $term_name = preg_replace('/s$/', '', $term_name);

    if (in_array($term_name, $normalized)) {
      // @todo Remove the debug message.
      print "Term $term_name found and reused.\n";
      return array_search($term_name, $normalized);
    }

    // If we are here, create the term, pass it to the normalized array and
    // return the term id.
    $term = Term::create([
      'name' => $term_name,
      'vid' => 'keywords',
      'status' => 1,
      'created' => $time,
    ]);
    /** @var \Drupal\taxonomy\TermInterface $term */
    $term->save();
    $normalized[(int) $term->id()] = $term->label();

    // @todo Remove the debug message.
    print "Term $term_name created.\n";

    return (int) $term->id();
  };

  $assign_term_to_entity = function (array $data, int $term_id): void {
    foreach (['node__keywords', 'node_revision__keywords'] as $table) {
      \Drupal::database()->insert($table)
        ->fields([
          'bundle' => $data['bundle'],
          'deleted' => 0,
          'entity_id' => $data['entity_id'],
          'revision_id' => $data['revision_id'],
          'langcode' => 'en',
          'delta' => $data['delta'],
          'keywords_target_id' => $term_id,
        ])
        ->execute();
    }
  };

  $entries = array_splice($sandbox['data'], 0, 10);
  foreach ($entries as $data) {
    foreach ($data as $entry) {
      // Check if the term already exists.
      $term_id = $get_normalized_id($entry['field_keywords_value'], $sandbox['normalized']);

      // Assign the term to the entity.
      $assign_term_to_entity($entry, $term_id);
    }
  }

  $sandbox['current'] += count($entries);
  $sandbox['progress'] = round($sandbox['current'] / $sandbox['count'] * 100, 2);
  $sandbox['#finished'] = $sandbox['current'] >= $sandbox['count'];

  if ($sandbox['#finished']) {
    \Drupal::state()->delete('joinup_core.keywords');
  }
  return sprintf('Processed %s of %s keywords.', $sandbox['current'], $sandbox['count']);
}
