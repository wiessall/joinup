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

  // List of allowed EU language names.
  $eu_language_names = [
    'Bulgarian',
    'Croatian',
    'Czech',
    'Danish',
    'Dutch',
    'English',
    'Estonian',
    'Finnish',
    'French',
    'German',
    'Greek',
    'Hungarian',
    'Irish',
    'Italian',
    'Latvian',
    'Lithuanian',
    'Maltese',
    'Polish',
    'Portuguese',
    'Romanian',
    'Slovak',
    'Slovenian',
    'Spanish',
    'Swedish',
  ];

  // Query to get taxonomy term IDs for EU languages.
  $query = $database->select('taxonomy_term_field_data', 'tt');
  $query->fields('tt', ['tid']);
  $query->condition('tt.vid', 'language');
  $query->condition('tt.name', $eu_language_names, 'IN');
  $allowed_language_tids = $query->execute()->fetchCol();

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
