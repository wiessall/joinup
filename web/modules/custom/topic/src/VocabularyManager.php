<?php

declare(strict_types=1);

namespace Drupal\topic;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\meta_entity\MetaEntityRepositoryInterface;
use Drupal\sparql_entity_storage\Driver\Database\sparql\ConnectionInterface;

/**
 * Helper class for managing the topic vocabulary.
 *
 * This class is to be used for update purposes only.
 */
class VocabularyManager {

  /**
   * Constructs a VocabularyManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\sparql_entity_storage\Driver\Database\sparql\ConnectionInterface $sparqlEndpoint
   *   The sparql.endpoint service.
   * @param \Drupal\meta_entity\MetaEntityRepositoryInterface $metaEntityRepository
   *   The meta_entity.repository service.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected Connection $database,
    protected ConnectionInterface $sparqlEndpoint,
    protected MetaEntityRepositoryInterface $metaEntityRepository,
  ) {
  }

  /**
   * Retrieves the internal path of the taxonomy term.
   */
  public function getPath(string $uri): string {
    return $this->entityTypeManager
      ->getStorage('taxonomy_term')
      ->load($uri)
      ->toUrl()
      ->toString();
  }

  /**
   * Creates a topic term.
   *
   * @param string $name
   *   The name of the term.
   * @param string|null $parent
   *   (optional) The parent of the term.
   */
  public function createTopicTerm(string $name, ?string $parent = NULL): void {
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');

    if (!empty($parent)) {
      $parent = $term_storage->load($parent);
    }

    // The ID of the topic will be assigned automatically according to the name.
    // @see \Drupal\joinup_core\Plugin\sparql_entity_storage\Id\TopicVocabulary::generate()
    $term_storage->create([
      'vid' => 'topic',
      'name' => $name,
      'parent' => $parent,
    ])->save();
  }

  /**
   * Renames a topic term.
   *
   * @param string $id
   *   The ID of the topic to rename.
   * @param string $newName
   *   The new name of the term.
   */
  public function renameTopic(string $id, string $newName): void {
    $this->entityTypeManager
      ->getStorage('taxonomy_term')
      ->load($id)
      ->setName($newName)
      ->save();
  }

  /**
   * Changes the topic ID of the term.
   *
   * @param string $oldId
   *   The old ID of the term.
   * @param string $newId
   *   The new ID of the term.
   */
  public function changeTopicId(string $oldId, string $newId): void {
    $oldTerm = $this->entityTypeManager->getStorage('taxonomy_term')->load($oldId);
    $meta_entity = $this->metaEntityRepository->getMetaEntityForEntity($oldTerm, 'topic');

    $oldPath = $this->getPath($oldId);
    $query = <<<QUERY
    WITH <http://topic>
    DELETE { <$oldId> ?p ?o }
    INSERT { <$newId> ?p ?o }
    WHERE { <$oldId> ?p ?o }
    QUERY;
    $this->sparqlEndpoint->query($query);

    $newPath = $this->getPath($newId);
    $this->createRedirect($oldPath, $newPath);
    $newTerm = $this->entityTypeManager->getStorage('taxonomy_term')->load($newId);

    // Also, set the meta entity relation ID.
    /** @var \Drupal\meta_entity\Entity\MetaEntityInterface $meta_entity */
    $meta_entity->set('target', $newTerm);
    $meta_entity->save();
  }

  /**
   * Change the topic parent.
   *
   * @param string $id
   *   The ID of the term.
   * @param string $newParentId
   *   The ID of the new parent.
   */
  public function changeTopicParent(string $id, string $newParentId): void {
    $query = <<<QUERY
    WITH <http://topic>
    DELETE { <$id> <http://www.w3.org/2004/02/skos/core#broaderTransitive> ?parent }
    INSERT { <$id> <http://www.w3.org/2004/02/skos/core#broaderTransitive> <{$newParentId}> }
    WHERE { <$id> <http://www.w3.org/2004/02/skos/core#broaderTransitive> ?parent }
    QUERY;
    $this->sparqlEndpoint->query($query);
  }

  /**
   * Creates a redirect between 2 taxonomy terms.
   *
   * @param string $oldPath
   *   The old path of the term.
   * @param string $newPath
   *   The new path of the term.
   */
  public function createRedirect(string $oldPath, string $newPath): void {
    $redirect = $this->entityTypeManager
      ->getStorage('redirect')
      ->create([
        'redirect_source' => ['path' => $oldPath],
        'redirect_redirect' => ['uri' => $newPath],
        'language' => 'und',
        'status_code' => 301,
      ]);
    $redirect->save();
  }

  /**
   * Moves children of a topic category to a new parent.
   *
   * @param string $oldParentId
   *   The old parent ID.
   * @param string $newParentId
   *   The new parent ID.
   */
  public function moveTopicChildrenToNewParent(string $oldParentId, string $newParentId): void {
    $query = <<<QUERY
    WITH <http://topic>
    DELETE { ?child <http://www.w3.org/2004/02/skos/core#broaderTransitive> <{$oldParentId}> }
    INSERT { ?child <http://www.w3.org/2004/02/skos/core#broaderTransitive> <{$newParentId}> }
    WHERE { ?child <http://www.w3.org/2004/02/skos/core#broaderTransitive> <{$oldParentId}> }
    QUERY;
    $this->sparqlEndpoint->query($query);
  }

  /**
   * Updates references to a topic in the databases to a new topic.
   *
   * @param string $oldId
   *   The old reference ID.
   * @param string $newId
   *   The new reference ID.
   */
  public function updateDbReference(string $oldId, string $newId): void {
    $this->updateMysqlEntityReferences($oldId, $newId);
    $this->updateSparqlEntityReferences($oldId, $newId);
  }

  /**
   * Updates references to a topic in the SPARQL database to a new topic.
   *
   * @param string $oldId
   *   The old reference ID.
   * @param string $newId
   *   The new reference ID.
   */
  public function updateSparqlEntityReferences(string $oldId, string $newId): void {
    $graphs = [
      'http://joinup.eu/collection/published',
      'http://joinup.eu/collection/draft',
      'http://joinup.eu/solution/published',
      'http://joinup.eu/solution/draft',
      'http://joinup.eu/asset_release/published',
      'http://joinup.eu/asset_release/draft',
    ];

    foreach ($graphs as $graph) {
      $query = <<<QUERY
      WITH <{$graph}>
      DELETE { ?entity <http://joinup.eu/vocabulary/topic> <{$oldId}> }
      INSERT { ?entity <http://joinup.eu/vocabulary/topic> <{$newId}> }
      WHERE { ?entity <http://joinup.eu/vocabulary/topic> <{$oldId}> }
      QUERY;
      $this->sparqlEndpoint->query($query);
    }
  }

  /**
   * Updates references to a topic in the SQL database to a new topic.
   *
   * @param string $oldId
   *   The old reference ID.
   * @param string $newId
   *   The new reference ID.
   */
  protected function updateMysqlEntityReferences(string $oldId, string $newId): void {
    $this->database->update('node__field_topic')
      ->fields(['field_topic_target_id' => $newId])
      ->condition('field_topic_target_id', $oldId)
      ->execute();

    // Do the query also for the revisions table.
    $this->database->update('node_revision__field_topic')
      ->fields(['field_topic_target_id' => $newId])
      ->condition('field_topic_target_id', $oldId)
      ->execute();

    // The users also refer to the vocabulary via the
    // field_user_professional_domain field.
    $this->database->update('user__field_user_professional_domain')
      ->fields(['field_user_professional_domain_target_id' => $newId])
      ->condition('field_user_professional_domain_target_id', $oldId)
      ->execute();
  }

  /**
   * Deletes a topic term.
   *
   * @param string $uri
   *   The term URI.
   */
  public function deleteTopicTerm(string $uri): void {
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $term = $term_storage->load($uri);
    $term->delete();
  }

}
