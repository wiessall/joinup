<?php

/**
 * @file
 * Deploy functions for Joinup.
 *
 * This should only contain update functions that rely on the Drupal API and
 * need to run _after_ the configuration is imported.
 *
 * This is applicable in most cases. However, in case the update code enables
 * some functionality that is required for configuration to be successfully
 * imported, it should instead be placed in joinup_core.post_update.php.
 */

declare(strict_types=1);

use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use EasyRdf\Graph;

/**
 * Deploy the eGovERA vocabulary.
 */
function joinup_core_deploy_200200(): void {
  $graph = new Graph();
  $graph->parseFile('../resources/vocab/eGovERA.rdf');
  \Drupal::service('joinup_taxonomy.vocabulary_updater')
    ->registerGraph('http://data.europa.eu/dr8', $graph)
    ->registerVocabulary('egovera', 'http://data.europa.eu/dr8', 'http://data.europa.eu/dr8')
    ->update();
}

/**
 * Create 'Upcoming events' page.
 */
function joinup_core_deploy_200201(): void {
  $node = Node::create([
    'type' => 'custom_page',
    'title' => 'Events calendar',
    'status' => TRUE,
    'path' => [
      'alias' => '/upcoming-events',
      'langcode' => 'en',
      'pathauto' => 0,
    ],
    'field_paragraphs_body' => [
      Paragraph::create([
        'type' => 'block',
        'paragraph_block' => [
          'plugin_id' => 'views_block:events_calendar-upcoming_events_block',
          'settings' => [],
        ],
      ]),
    ],
    'field_hide_title' => FALSE,
    'field_state' => 'published',
    'abstract' => 'Upcoming events',
  ]);
  $node->save();
}
