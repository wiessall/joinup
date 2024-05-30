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

use Drupal\node\NodeInterface;
use Drupal\rdf_entity\RdfInterface;
use Drupal\search_api\Plugin\search_api\datasource\ContentEntity;
use Drupal\topic\VocabularyManager;

define("STAT_INFO_TERM_OLD_ID", "http://joinup.eu/ontology/topic#statistics-and-informatino");
define("STAT_INFO_TERM_NEW_ID", "http://joinup.eu/ontology/topic#statistics-and-information");

/**
 * Fix typo in Statistics and information term URI.
 */
function joinup_core_deploy_110700(): void {
  $connection = \Drupal::database();
  $termId = $connection->select('rdf_sync_uri', 'rsu')
    ->fields('rsu', ['entity_id'])
    ->condition('entity_type', 'taxonomy_term')
    ->condition('uri', STAT_INFO_TERM_OLD_ID)
    ->execute()
    ->fetchField();
  \Drupal::database()->update('rdf_sync_uri')
    ->condition('entity_id', $termId)
    ->condition('entity_type', 'taxonomy_term')
    ->fields(['uri' => STAT_INFO_TERM_NEW_ID])
    ->execute();

  // Update URI value in RDF entities.
  $vocabularyManager = \Drupal::service('topic.vocabulary_manager');
  assert($vocabularyManager instanceof VocabularyManager);
  $vocabularyManager->updateSparqlEntityReferences(
    STAT_INFO_TERM_OLD_ID,
    STAT_INFO_TERM_NEW_ID
  );

  $entityTypeManager = \Drupal::entityTypeManager();
  // Reindex nodes having this term.
  $nodeHavingNewTermId = \Drupal::database()->select('node__field_topic', 'nft')
    ->fields('nft', ['entity_id'])
    ->condition('field_topic_target_id', $termId)
    ->execute();
  $nodeStorage = $entityTypeManager->getStorage('node');
  foreach ($nodeHavingNewTermId->fetchAllKeyed(0, 0) as $nodeId) {
    $node = $nodeStorage->load($nodeId);
    assert($node instanceof NodeInterface);
    foreach (ContentEntity::getIndexesForEntity($node) as $index) {
      $id = implode(':', [$node->id(), $node->language()->getId()]);
      $index->trackItemsUpdated('entity:node', [$id]);
    }
  }

  // Reindex RDF entities having this term.
  $sparqlConnection = \Drupal::getContainer()->get('sparql.endpoint');
  $newTermId = STAT_INFO_TERM_NEW_ID;
  $query = <<<SPARQL
SELECT ?graph ?entity
WHERE {
  GRAPH ?graph {
      ?entity <http://joinup.eu/vocabulary/topic> <$newTermId> .
  }
}
SPARQL;

  $results = $sparqlConnection->query($query);
  $sparqlEntityStorage = $entityTypeManager->getStorage('rdf_entity');
  foreach ($results as $result) {
    $rdf = $sparqlEntityStorage->load($result->entity->getUri());
    assert($rdf instanceof RdfInterface);
    foreach (ContentEntity::getIndexesForEntity($rdf) as $index) {
      $id = implode(':', [$rdf->id(), $rdf->language()->getId()]);
      $index->trackItemsUpdated('entity:rdf_entity', [$id]);
    }
  }

}
