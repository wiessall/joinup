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

declare(strict_types = 1);

/**
 * Clean up banner files.
 */
function joinup_core_deploy_109600(array &$sandbox): string {
  if (empty($sandbox['fids'])) {
    $query = <<<SPARQL
select ?o
from <http://joinup.eu/collection/published>
from <http://joinup.eu/collection/draft>
from <http://joinup.eu/solution/published>
from <http://joinup.eu/solution/draft>
from <http://joinup.eu/asset_release/published>
from <http://joinup.eu/asset_release/draft>
where { ?s <http://xmlns.com/foaf/0.1/Image> ?o }
SPARQL;

    $sparql = \Drupal::getContainer()->get('sparql.endpoint');
    $results = $sparql->query($query);
    foreach ($results as $result) {
      $sandbox['fids'][] = $result->o->getValue();
    }
    $sandbox['total'] = count($sandbox['fids']);
    $sandbox['current'] = 0;
  }

  $fids = array_splice($sandbox['fids'], 0, 100);
  $file_storage = \Drupal::entityTypeManager()->getStorage('file');
  $file_storage->delete($file_storage->loadMultiple($fids));

  $sandbox['current'] += count($fids);
  $sandbox['#finished'] = $sandbox['current'] / $sandbox['total'];
  return 'Processed ' . $sandbox['current'] . ' of ' . $sandbox['total'] . ' files.';
}

/**
 * Clean up banner database entries.
 */
function joinup_core_deploy_109601(): void {
  $sparql_endpoint = \Drupal::getContainer()->get('sparql.endpoint');
  $query = <<<SPARQL
DELETE { GRAPH ?g { ?s <http://xmlns.com/foaf/0.1/Image> ?o } }
WHERE {
  GRAPH ?g { ?s <http://xmlns.com/foaf/0.1/Image> ?o } .
  VALUES ?g { <http://joinup.eu/collection/published>  <http://joinup.eu/collection/draft> <http://joinup.eu/solution/published> <http://joinup.eu/solution/draft> <http://joinup.eu/asset_release/published> <http://joinup.eu/asset_release/draft> }
}
SPARQL;

  $sparql_endpoint->update($query);
}

use Drupal\search_api\Entity\Index;

/**
 * Update the topics list.
 */
function joinup_core_deploy_109602(): string {
  $vocabulary_manager = \Drupal::getContainer()->get('topic.vocabulary_manager');
  $messages = [];

  // The former Innovation must be renamed to 'Digital Innovation' and keep the
  // linked content items.
  $vocabulary_manager->renameTopic('http://joinup.eu/ontology/topic#innovation', 'Digital innovation');
  $messages[] = 'Topic "Innovation" renamed to "Digital innovation".';
  $vocabulary_manager->changeTopicId('http://joinup.eu/ontology/topic#innovation', 'http://joinup.eu/ontology/topic#digital-innovation');
  $vocabulary_manager->updateDbReference('http://joinup.eu/ontology/topic#innovation', 'http://joinup.eu/ontology/topic#digital-innovation');
  $messages[] = 'Topic ID "http://joinup.eu/ontology/topic#innovation" changed to "http://joinup.eu/ontology/topic#digital-innovation".';

  // A new parent category must be created, named 'Innovation'.
  $vocabulary_manager->createTopicTerm('Innovation');
  $messages[] = 'Topic category "Innovation" created.';

  // Under the new parent category, the following topics must be moved:
  // * Artificial Intelligence (move it by ICT parent topic).
  // * Blockchain (move it by ICT parent topic).
  // * 'Digital Innovation' (former Innovation).
  $vocabulary_manager->changeTopicParent('http://joinup.eu/ontology/topic#artificial-intelligence', 'http://joinup.eu/ontology/topic/category#innovation');
  $vocabulary_manager->changeTopicParent('http://joinup.eu/ontology/topic#blockchain', 'http://joinup.eu/ontology/topic/category#innovation');
  $vocabulary_manager->changeTopicParent('http://joinup.eu/ontology/topic#digital-innovation', 'http://joinup.eu/ontology/topic/category#innovation');
  $messages[] = 'Topics "Artificial Intelligence", "Blockchain" and "Digital Innovation" moved to "Innovation" category.';

  // Under the new parent category, the following topics must be added:
  // * Advanced data analytics
  // * AI Chatbot
  // * AI Facial recognition
  // * AI Image generation
  // * AI Machine translation
  // * AI Named entity recognition
  // * AI Speech recognition
  // * AI Video analytics
  // * Extended reality
  // * High performance computing
  // * Indoor position systems
  // * Internet of Things
  // * Optical Character Recognition
  // * Robotic Process Automation.
  $labels = [
    'Advanced data analytics',
    'AI Chatbot',
    'AI Facial recognition',
    'AI Image generation',
    'AI Machine translation',
    'AI Named entity recognition',
    'AI Speech recognition',
    'AI Video analytics',
    'Extended reality',
    'High performance computing',
    'Indoor position systems',
    'Internet of Things',
    'Optical Character Recognition',
    'Robotic Process Automation',
  ];

  foreach ($labels as $label) {
    $vocabulary_manager->createTopicTerm($label, 'http://joinup.eu/ontology/topic/category#innovation');
    $messages[] = "Topic \"$label\" created.";
  }

  return implode(PHP_EOL, $messages);
}

/**
 * Re-indexes items related to the affected changed terms.
 */
function joinup_core_deploy_109603(): string {
  $affected_terms = [
    'http://joinup.eu/ontology/topic/category#innovation',
    'http://joinup.eu/ontology/topic#innovation',
    'http://joinup.eu/ontology/topic#artificial-intelligence',
    'http://joinup.eu/ontology/topic#blockchain',
    'http://joinup.eu/ontology/topic#digital-innovation',
  ];

  $topic_ids = \Drupal::entityQuery('taxonomy_term')
    ->condition('vid', 'topic')
    ->accessCheck(FALSE)
    ->execute();
  $search_ids = array_map(function (string $id): string {
    return "entity:taxonomy_term/$id:en";
  }, $topic_ids);

  $node_ids = \Drupal::entityQuery('node')
    ->condition('field_topic', $affected_terms, 'IN')
    ->accessCheck(FALSE)
    ->execute();
  $search_ids = array_merge($search_ids, array_map(function (string $id): string {
    return "entity:node/$id:en";
  }, $node_ids));

  $rdf_entity_ids = \Drupal::entityQuery('rdf_entity')
    ->condition('field_topic', $affected_terms, 'IN')
    ->accessCheck(FALSE)
    ->execute();
  $search_ids = array_merge($search_ids, array_map(function (string $id): string {
    return "entity:rdf_entity/$id:en";
  }, $rdf_entity_ids));

  $index = Index::load('published');
  $items = $index->loadItemsMultiple($search_ids);
  $index->indexSpecificItems($items);

  return 'Items re-indexed.';
}
