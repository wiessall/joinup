<?php

/**
 * @file
 * Deploy functions for the joinup_solution module.
 */

declare(strict_types=1);

use Drupal\Core\Database\Database;

/**
 * Clean language records for solution nodes, keeping only EU languages.
 */
function joinup_solution_deploy_200600(): string {
  $database = Database::getConnection();

  // List of allowed EU language taxonomy term IDs.
  $allowed_language_tids = [
    10872, // Bulgarian
    10924, // Croatian
    10881, // Czech
    10892, // Danish
    10972, // Dutch
    10857, // English
    10900, // Estonian
    10906, // Finnish
    10907, // French
    10893, // German
    10897, // Greek
    10926, // Hungarian
    10911, // Irish
    10932, // Italian
    10949, // Latvian
    10952, // Lithuanian
    10960, // Maltese
    10984, // Polish
    10985, // Portuguese
    10995, // Romanian
    11004, // Slovak
    11005, // Slovenian
    11010, // Spanish
    11017, // Swedish
  ];

  // Clean node__language table.
  $query = $database->delete('node__language');
  $query->condition('bundle', 'solution');
  $query->condition('language_target_id', $allowed_language_tids, 'NOT IN');
  $deleted_node_language = $query->execute();

  // Clean node_revision__language table.
  $query = $database->delete('node_revision__language');
  $query->condition('bundle', 'solution');
  $query->condition('language_target_id', $allowed_language_tids, 'NOT IN');
  $deleted_node_revision_language = $query->execute();

  return sprintf('Cleaned %d records from node__language and %d records from node_revision__language for solution nodes with non-EU languages.', $deleted_node_language, $deleted_node_revision_language);
}
