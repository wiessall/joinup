<?php

declare(strict_types=1);

namespace Drupal\joinup_i18n\Update;

use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\tmgmt\JobItemInterface;
use Drush\Drush;

/**
 * Repairs and re-queues content mangled by TMGMT's machine-value translation.
 *
 * TMGMT translated machine field values and wrote the mangled result back onto
 * the source content. This deploy-time recovery does two things:
 * - repairData(): restore the corrupted field values across all revisions;
 * - rescheduleJobItems(): abort the items its bad data left stuck in review and
 *   re-queue the sources so they re-extract cleanly.
 */
final class TmgmtRecovery implements ContainerInjectionInterface {

  use AutowireTrait;

  public function __construct(
    protected Connection $database,
    protected BlockManagerInterface $blockManager,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected KeyValueFactoryInterface $keyValue,
  ) {}

  /**
   * Main entrypoint.
   */
  public function run(): void {
    $this->repairData();
    $this->rescheduleJobItems();
  }

  /**
   * Repairs every corrupted field value, in one transaction.
   */
  private function repairData(): void {
    $transaction = $this->database->startTransaction();
    try {
      $this->repairWebform();
      $this->repairJson();
      $this->repairIframe();
      $this->repairSocialMedia();
      $this->repairParagraphBlock();
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }
  }

  /**
   * Reschedules the continuous job items left stuck in review.
   *
   * Stuck items hold the old corrupted data, so they are aborted rather than
   * re-accepted (which would replay the corruption); their source is re-queued
   * so a fresh extraction under the current code no longer includes the machine
   * properties. Sources that cannot be translated now (deleted, or blocked by a
   * pending revision) are only aborted.
   */
  private function rescheduleJobItems(): void {
    $storage = $this->entityTypeManager->getStorage('tmgmt_job_item');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('plugin', 'content')
      ->condition('state', JobItemInterface::STATE_REVIEW)
      ->execute();

    $aborted = 0;
    $sources = [];
    foreach ($storage->loadMultiple($ids) as $item) {
      \assert($item instanceof JobItemInterface);
      $aborted++;

      $entity_storage = $this->entityTypeManager->getStorage($item->getItemType());
      $entity = $entity_storage->load($item->getItemId());

      // A deleted source has nothing left to re-schedule.
      if (!$entity instanceof ContentEntityInterface) {
        $item->setState(JobItemInterface::STATE_ABORTED, 'Aborted: the source entity no longer exists.');
        continue;
      }

      // A pending forward revision blocks the accept while a draft exists
      // (which is why it stuck); it re-queues on publish via
      // tmgmt_content_entity_update(), so only abort it here.
      if ($entity_storage instanceof RevisionableStorageInterface && (int) $entity_storage->getLatestRevisionId($entity->id()) !== (int) $entity->getRevisionId()) {
        $item->setState(JobItemInterface::STATE_ABORTED, 'Aborted: the source entity has a pending draft revision; it re-queues on publish.');
        continue;
      }

      $item->setState(JobItemInterface::STATE_ABORTED, 'Aborted: re-scheduled for re-translation after the TMGMT fixes.');
      $sources[$item->getItemType() . ':' . $entity->id()] = $entity;
    }

    foreach ($sources as $entity) {
      tmgmt_content_create_continuous_job_items($entity);
    }

    Drush::logger()->notice(sprintf('Rescheduled job items: aborted %d stuck items, re-queued %d source entities.', $aborted, count($sources)));
  }

  /**
   * Repairs mangled webform reference IDs and statuses.
   */
  protected function repairWebform(): void {
    $ids = $this->entityTypeManager->getStorage('webform')->getQuery()->accessCheck(FALSE)->execute();
    $webform_ids = \array_flip($ids);
    $statuses = ['open' => TRUE, 'closed' => TRUE, 'scheduled' => TRUE];
    $this->repairFromNearestValidValue('paragraph_webform', [
      'paragraph_webform_target_id' => fn (?string $value): bool => $value === NULL || $value === '' || isset($webform_ids[$value]),
      'paragraph_webform_status' => fn (?string $value): bool => $value === NULL || $value === '' || isset($statuses[$value]),
    ]);
  }

  /**
   * Repairs Webtools map JSON blobs translated into non-parsing prose.
   */
  protected function repairJson(): void {
    $this->repairFromNearestValidValue('field_json', [
      'field_json_value' => fn (?string $value): bool => $value === NULL || trim($value) === '' || json_validate($value),
    ]);
  }

  /**
   * Repairs the iframe scrolling mode translated away from its machine value.
   */
  public function repairIframe(): void {
    $modes = ['auto' => TRUE, 'yes' => TRUE, 'no' => TRUE];
    $this->repairFromNearestValidValue('field_iframe', [
      'field_iframe_scrolling' => fn (?string $value): bool => $value === NULL || $value === '' || isset($modes[$value]),
    ]);
  }

  /**
   * Repairs social-media platform keys and account handles.
   */
  protected function repairSocialMedia(): void {
    $field_name = 'field_paragraphs_social_media';
    $platform = $field_name . '_platform';
    $value = $field_name . '_value';

    [$data_table, $revision_table] = $this->getFieldTables($field_name);

    // Fix the revision history: force every translated row's machine values to
    // match its English source row (same paragraph, revision and delta).
    $sources = $this->database->select($revision_table, 't')
      ->fields('t', ['entity_id', 'revision_id', 'delta', $platform, $value])
      ->condition('langcode', 'en')
      ->execute();
    $repaired = 0;
    foreach ($sources as $source) {
      $repaired += $this->database->update($revision_table)
        ->fields([
          $platform => $source->{$platform},
          $value => $source->{$value},
        ])
        ->condition('entity_id', $source->entity_id)
        ->condition('revision_id', $source->revision_id)
        ->condition('delta', $source->delta)
        ->condition('langcode', 'en', '<>')
        ->execute();
    }
    Drush::logger()->notice(sprintf('%s: repaired %d row(s) in the revision table.', $field_name, $repaired));

    $synced = $this->syncDataFromRevision($data_table, $revision_table, [$platform, $value]);
    Drush::logger()->notice(sprintf('%s: repaired %d row(s) in the data table.', $field_name, $synced));
  }

  /**
   * Repairs block plugin IDs translated away from their machine value.
   */
  protected function repairParagraphBlock(): void {
    // e.g. "views_block:calendar-by_week" translated to
    // "views_block: календар-by_week"; valid = a registered block plugin ID.
    $this->repairFromNearestValidValue('paragraph_block', [
      'paragraph_block_plugin_id' => fn (?string $value): bool => $value === NULL || $value === '' || $this->blockManager->hasDefinition($value),
    ]);
  }

  /**
   * Restores corrupted columns from the nearest valid value in history.
   */
  protected function repairFromNearestValidValue(string $field_name, array $validators): void {
    [$data_table, $revision_table] = $this->getFieldTables($field_name);
    $columns = \array_keys($validators);

    $rows = $this->database->select($revision_table, 't')
      ->fields('t', \array_merge(['entity_id', 'revision_id', 'delta', 'langcode'], $columns))
      ->orderBy('entity_id')
      ->orderBy('delta')
      ->orderBy('revision_id')
      ->execute()
      ->fetchAll();

    // Only invalid values are written, so editor changes are never reverted.
    $repaired = 0;
    $last_valid = [];
    foreach ($rows as $row) {
      $fields = [];
      foreach ($validators as $column => $is_valid) {
        if ($is_valid($row->{$column})) {
          $last_valid[$row->entity_id][$row->delta][$column] = $row->{$column};
          continue;
        }
        $value = $last_valid[$row->entity_id][$row->delta][$column] ?? NULL;
        if ($value !== NULL) {
          $fields[$column] = $value;
        }
      }

      if ($fields) {
        $repaired += $this->database->update($revision_table)
          ->fields($fields)
          ->condition('revision_id', $row->revision_id)
          ->condition('delta', $row->delta)
          ->condition('langcode', $row->langcode)
          ->execute();
      }
    }
    Drush::logger()->notice(sprintf('%s: repaired %d row(s) in the revision table.', $field_name, $repaired));

    $synced = $this->syncDataFromRevision($data_table, $revision_table, $columns);
    Drush::logger()->notice(sprintf('%s: repaired %d row(s) in the data table.', $field_name, $synced));
  }

  /**
   * Copies the repaired columns from the revision table into the data table.
   *
   * The data table holds the default-revision rows, a subset of the revision
   * table, so once the history is fixed the data table is derived from it.
   */
  protected function syncDataFromRevision(string $data_table, string $revision_table, array $columns): int {
    $assignments = [];
    $diverged = [];
    foreach ($columns as $column) {
      $assignments[] = "[d].[$column] = [r].[$column]";
      // Compare bytes so case-only corruption is caught on CI collations.
      $diverged[] = "NOT (CAST([d].[$column] AS BINARY) <=> CAST([r].[$column] AS BINARY))";
    }

    $statement = $this->database->prepareStatement(
      "UPDATE {" . $data_table . "} [d]
       INNER JOIN {" . $revision_table . "} [r] ON [r].[entity_id] = [d].[entity_id]
         AND [r].[revision_id] = [d].[revision_id]
         AND [r].[delta] = [d].[delta]
         AND [r].[langcode] = [d].[langcode]
       SET " . implode(', ', $assignments) . "
       WHERE " . implode(' OR ', $diverged),
      [],
      TRUE,
    );
    $statement->execute();
    return $statement->rowCount();
  }

  /**
   * Returns the data and revision tables of a paragraph field.
   */
  protected function getFieldTables(string $field_name): array {
    return \array_keys($this->keyValue->get('entity.storage_schema.sql')->get('paragraph.field_schema_data.' . $field_name, []));
  }

}
