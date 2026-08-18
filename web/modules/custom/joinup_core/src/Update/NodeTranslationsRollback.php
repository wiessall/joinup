<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Update;

use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drush\Drush;

/**
 * Rolls back nodes that lost paragraph references during translation saves.
 *
 * A revision created by a translation save sometimes references fewer
 * paragraphs than the last revision an editor saved. Every damaged node is
 * returned to the state of that editor revision, and the revisions the
 * translation jobs created are deleted. The translations are discarded
 * knowingly: paragraphs translated while they were not referenced never
 * received the translation, so they are incomplete either way. The save
 * schedules a fresh translation of every language through the regular TMGMT
 * continuous job process.
 */
final class NodeTranslationsRollback implements ContainerInjectionInterface {

  use AutowireTrait;

  /**
   * The node fields referencing paragraph revisions.
   *
   * @var string[]
   */
  private array $paragraphFieldNames;

  public function __construct(
    protected Connection $database,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityFieldManagerInterface $entityFieldManager,
  ) {}

  /**
   * Main entrypoint.
   */
  public function run(): string {
    $storage = $this->entityTypeManager->getStorage('node');
    $damaged = $this->getDamagedNodeIds();
    $rolled_back = 0;
    $skipped = [];

    foreach ($storage->loadMultiple($damaged) as $node) {
      \assert($node instanceof NodeInterface);
      $nid = (int) $node->id();
      // A new default revision on top of a pending draft would bury the
      // draft; leave such nodes to a manual pass.
      if ((int) $storage->getLatestRevisionId($nid) !== (int) $node->getRevisionId()) {
        Drush::logger()->warning(sprintf('Node %d was not rolled back: it has a pending forward revision.', $nid));
        $skipped[] = $nid;
        continue;
      }

      $this->rollback($nid);
      $rolled_back++;
    }

    // The rollback must leave no damage behind, except on nodes it declined
    // to touch: rerunning the detection is the integrity check. A failure
    // aborts the deployment.
    $remaining = array_diff($this->getDamagedNodeIds(), $skipped);
    if ($remaining) {
      throw new \RuntimeException(sprintf('The rollback left %d node(s) damaged: %s.', count($remaining), implode(', ', $remaining)));
    }

    return sprintf('Rolled back %d of %d damaged node(s).', $rolled_back, count($damaged));
  }

  /**
   * Returns the IDs of the damaged nodes.
   */
  protected function getDamagedNodeIds(): array {
    // Any node that was ever saved by a translation job is a candidate.
    $query = $this->database->select('node_revision', 'r')
      ->fields('r', ['nid'])
      ->condition('r.revision_log', 'Created by translation job%', 'LIKE')
      ->distinct();

    $damaged = [];
    foreach ($query->execute()->fetchCol() as $nid) {
      $nid = (int) $nid;
      $source_vid = $this->getLastEditorRevisionId($nid);
      if ($source_vid && $this->hasLostReferences($nid, $source_vid)) {
        $damaged[] = $nid;
      }
    }

    return $damaged;
  }

  /**
   * Returns the ID of the last revision that was not created by TMGMT.
   */
  protected function getLastEditorRevisionId(int $nid): ?int {
    $query = $this->database->select('node_revision', 'r');
    $query->fields('r', ['vid']);
    $query->condition('r.nid', $nid);
    $group = $query->orConditionGroup()
      ->isNull('r.revision_log')
      ->condition('r.revision_log', 'Created by translation job%', 'NOT LIKE');
    $query->condition($group);
    $query->orderBy('r.vid', 'DESC');
    $query->range(0, 1);
    $vid = $query->execute()->fetchField();

    return $vid ? (int) $vid : NULL;
  }

  /**
   * Checks whether a node references fewer paragraphs than its source does.
   */
  protected function hasLostReferences(int $nid, int $source_vid): bool {
    foreach ($this->getParagraphFieldNames() as $field) {
      $source_count = (int) $this->database->select('node_revision__' . $field, 'f')
        ->condition('f.revision_id', $source_vid)
        ->countQuery()
        ->execute()
        ->fetchField();
      $current_count = (int) $this->database->select('node__' . $field, 'f')
        ->condition('f.entity_id', $nid)
        ->countQuery()
        ->execute()
        ->fetchField();
      if ($source_count > $current_count) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Returns a node to the state of its last editor revision.
   */
  protected function rollback(int $nid): void {
    $source_vid = $this->getLastEditorRevisionId($nid);
    \assert($source_vid !== NULL);

    // Saving the editor revision as the new default returns content,
    // references and translations to that state; the referenced paragraphs
    // are revisioned from their content of that era along with it.
    /** @var \Drupal\node\NodeInterface $source */
    $source = $this->entityTypeManager->getStorage('node')->loadRevision($source_vid);
    $source->setNewRevision();
    $source->isDefaultRevision(TRUE);
    $source->setRevisionLogMessage('Rolled back paragraph references lost while saving translations.');
    $source->setRevisionTranslationAffected(TRUE);
    $source->setSyncing(TRUE);
    $source->skip_notification = TRUE;
    $source->save();

    $deleted = $this->deleteTranslationRevisions($nid, $source_vid);
    Drush::logger()->notice(sprintf('Rolled back node %d to revision %d, deleted %d translation revision(s).', $nid, $source_vid, $deleted));
  }

  /**
   * Deletes the revisions the translation jobs created after the source.
   *
   * With the translations removed, these revisions only describe state that
   * no longer exists, and the damaged ones among them could revert the node
   * back into the loss. Editor revisions are never touched.
   */
  protected function deleteTranslationRevisions(int $nid, int $source_vid): int {
    $query = $this->database->select('node_revision', 'r');
    $query->fields('r', ['vid']);
    $query->condition('r.nid', $nid);
    $query->condition('r.vid', $source_vid, '>');
    $query->condition('r.revision_log', 'Created by translation job%', 'LIKE');

    $storage = $this->entityTypeManager->getStorage('node');
    $deleted = 0;
    foreach ($query->execute()->fetchCol() as $vid) {
      $storage->deleteRevision((int) $vid);
      $deleted++;
    }

    return $deleted;
  }

  /**
   * Returns the names of the node fields referencing paragraph revisions.
   *
   * @return string[]
   *   The field names.
   */
  protected function getParagraphFieldNames(): array {
    if (!isset($this->paragraphFieldNames)) {
      $this->paragraphFieldNames = [];
      foreach ($this->entityFieldManager->getFieldStorageDefinitions('node') as $name => $definition) {
        if ($definition->getType() === 'entity_reference_revisions' && $definition->getSetting('target_type') === 'paragraph') {
          $this->paragraphFieldNames[] = $name;
        }
      }
    }
    return $this->paragraphFieldNames;
  }

}
