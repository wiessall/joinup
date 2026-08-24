<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Update;

use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Clears revision_translation_affected where no translation was affected.
 *
 * Accepting a translation job item writes one language, so a revision it
 * created that claims several is suspect. Most of those were left by
 * ISAICP-11533: the values that once justified them were repaired afterward,
 * so the difference they describe no longer exists. The rest come from
 * translations saved concurrently on the same node.
 */
final class RevisionTranslationAffectedRepair implements ContainerInjectionInterface {

  use AutowireTrait;

  /**
   * Referenced paragraphs may nest, so the walk needs a floor.
   */
  private const int MAX_DEPTH = 6;

  /**
   * Revisions per pass; each loads two revisions and their paragraphs.
   */
  private const int BATCH_SIZE = 100;

  public function __construct(
    protected Connection $database,
    protected EntityTypeManagerInterface $entityTypeManager,
    #[Autowire('logger.channel.joinup_core')]
    protected LoggerChannelInterface $logger,
  ) {}

  /**
   * Repairs a slice of the affected revisions.
   *
   * @param array $sandbox
   *   The deploy hook sandbox.
   *
   * @return string
   *   A message describing what was done.
   */
  public function run(array &$sandbox): string {
    if (!isset($sandbox['revisions'])) {
      $sandbox['revisions'] = $this->getSuspectRevisions();
      $sandbox['total'] = count($sandbox['revisions']);
      $sandbox['cleared'] = 0;
      $sandbox['repaired'] = 0;
      $sandbox['skipped'] = 0;
      $sandbox['inspected'] = 0;
      $sandbox['report'] = [];
    }

    $storage = $this->entityTypeManager->getStorage('node');
    foreach (array_splice($sandbox['revisions'], 0, self::BATCH_SIZE) as $row) {
      $previousVid = $this->getPreviousRevisionId((int) $row->nid, (int) $row->vid);
      $revision = $previousVid ? $storage->loadRevision($row->vid) : NULL;
      $previous = $previousVid ? $storage->loadRevision($previousVid) : NULL;
      if ($revision instanceof ContentEntityInterface && $previous instanceof ContentEntityInterface) {
        $this->repairRevision($revision, $previous, $sandbox);
      }
      $storage->resetCache([$row->nid]);
      $sandbox['inspected']++;
    }

    $sandbox['#finished'] = (int) empty($sandbox['revisions']);

    if (!$sandbox['#finished']) {
      return sprintf('Inspected %d of %d revisions, cleared %d values.', $sandbox['inspected'], $sandbox['total'], $sandbox['cleared']);
    }

    $message = sprintf('Cleared %d values on %d of %d revisions.', $sandbox['cleared'], $sandbox['repaired'], $sandbox['total']);
    if ($sandbox['skipped']) {
      $message .= sprintf(' %d skipped, no language changed in them.', $sandbox['skipped']);
    }
    $message .= "\nReport:\n" . $this->buildReport($sandbox);

    return $message;
  }

  /**
   * Returns revisions created by a translation job claiming several languages.
   */
  protected function getSuspectRevisions(): array {
    $query = $this->database->select('node_revision', 'r');
    $query->innerJoin('node_field_revision', 'f', '[f].[vid] = [r].[vid] AND [f].[revision_translation_affected] = :affected', [':affected' => 1]);
    $query->fields('r', ['nid', 'vid']);
    $query->condition('r.revision_log', 'Created by translation job%', 'LIKE');
    $query->groupBy('r.nid');
    $query->groupBy('r.vid');
    $query->addExpression('COUNT([f].[langcode])', 'languages');
    $query->havingCondition('languages', 1, '>');
    return $query->execute()->fetchAll();
  }

  /**
   * Returns the ID of the revision preceding the given one.
   */
  protected function getPreviousRevisionId(int $nid, int $vid): ?int {
    $previousVid = $this->database->select('node_revision', 'r')
      ->fields('r', ['vid'])
      ->condition('r.nid', $nid)
      ->condition('r.vid', $vid, '<')
      ->orderBy('r.vid', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchField();
    return $previousVid ? (int) $previousVid : NULL;
  }

  /**
   * Clears the languages a revision claims without having changed them.
   */
  protected function repairRevision(ContentEntityInterface $revision, ContentEntityInterface $previous, array &$sandbox): void {
    $revision->setOriginal($previous);

    $affected = $unaffected = [];
    foreach (array_keys($revision->getTranslationLanguages()) as $langcode) {
      $translation = $revision->getTranslation($langcode);
      if (!$translation->isRevisionTranslationAffected()) {
        continue;
      }
      $translation->setOriginal($previous);
      $changed = $translation->hasTranslationChanges()
        || $this->paragraphsChanged($revision, $previous, $langcode);
      $changed ? $affected[] = $langcode : $unaffected[] = $langcode;
    }

    // node_revision_delete only selects revisions having
    // revision_translation_affected set, both to delete and to protect, so a
    // revision left without it in any language could never be pruned again.
    if (!$unaffected) {
      return;
    }
    if (!$affected) {
      $sandbox['skipped']++;
      return;
    }

    $sandbox['cleared'] += $this->database->update('node_field_revision')
      ->fields(['revision_translation_affected' => NULL])
      ->condition('vid', $revision->getRevisionId())
      ->condition('langcode', $unaffected, 'IN')
      ->condition('revision_translation_affected', 1)
      ->execute();
    $sandbox['repaired']++;
    $sandbox['report'][$revision->id()][$revision->getRevisionId()] = $unaffected;
  }

  /**
   * Checks whether a referenced paragraph changed for the given language.
   *
   * ContentEntityBase::hasTranslationChanges() stops at the host, which is why
   * EntityReferenceRevisionsItem::preSave() marks the host itself when a
   * paragraph it references changes. That rule is reproduced here.
   *
   * @see \Drupal\entity_reference_revisions\Plugin\Field\FieldType\EntityReferenceRevisionsItem::preSave()
   */
  protected function paragraphsChanged(ContentEntityInterface $revision, ContentEntityInterface $previous, string $langcode, int $depth = 0): bool {
    if ($depth > self::MAX_DEPTH) {
      return FALSE;
    }

    foreach ($revision->getFieldDefinitions() as $name => $definition) {
      if ($definition->getType() !== 'entity_reference_revisions') {
        continue;
      }

      $before = [];
      foreach ($previous->get($name) as $item) {
        if ($paragraph = $item->entity) {
          $before[$paragraph->id()] = $paragraph;
        }
      }

      $seen = [];
      foreach ($revision->get($name) as $item) {
        $paragraph = $item->entity;
        if (!$paragraph) {
          continue;
        }
        $seen[$paragraph->id()] = TRUE;
        // A paragraph that was not referenced before is a change the host
        // shares across all its languages.
        if (!isset($before[$paragraph->id()])) {
          return TRUE;
        }

        $old = $before[$paragraph->id()];
        if ($paragraph->hasTranslation($langcode) !== $old->hasTranslation($langcode)) {
          return TRUE;
        }
        if ($paragraph->hasTranslation($langcode)) {
          $translation = $paragraph->getTranslation($langcode);
          $translation->setOriginal($old);
          if ($translation->hasTranslationChanges()) {
            return TRUE;
          }
        }
        if ($this->paragraphsChanged($paragraph, $old, $langcode, $depth + 1)) {
          return TRUE;
        }
      }

      if (array_diff_key($before, $seen)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Builds a report of data that was cleared.
   *
   * @param array $sandbox
   *   The deploy hook sandbox.
   *
   * @return string
   *   A report of data that was cleared.
   */
  protected function buildReport(array $sandbox): string {
    $report = [];
    foreach ($sandbox['report'] as $nid => $revisions) {
      $report[] = Url::fromRoute('entity.node.version_history', [
        'node' => $nid,
      ])->setAbsolute()->toString() . " (nid: $nid):";
      foreach ($revisions as $vid => $languages) {
        sort($languages);
        $report[] = "- $vid: " . implode(', ', $languages);
      }
    }

    return implode("\n", $report);
  }

}
