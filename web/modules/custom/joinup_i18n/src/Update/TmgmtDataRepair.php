<?php

declare(strict_types=1);

namespace Drupal\joinup_i18n\Update;

use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Drush;

/**
 * Repairs field values that tmgmt translated and mangled.
 *
 * Some values must never be translated: untranslatable field instances and
 * machine properties of translatable fields. tmgmt sent them for
 * translation anyway and wrote the mangled result back. Each is restored from
 * the nearest valid value in the paragraph's history.
 *
 * @see https://www.drupal.org/project/tmgmt/issues/3382356
 */
final class TmgmtDataRepair implements ContainerInjectionInterface {

  use AutowireTrait;

  public function __construct(
    protected Connection $database,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Main entrypoint.
   */
  public function run(): void {
    $this->repairWebform();
    $this->repairJson();
  }

  /**
   * Repairs mangled webform reference IDs and statuses.
   */
  protected function repairWebform(): void {
    $ids = $this->entityTypeManager->getStorage('webform')->getQuery()->accessCheck(FALSE)->execute();
    $webform_ids = \array_flip($ids);
    $statuses = ['open' => TRUE, 'closed' => TRUE, 'scheduled' => TRUE];
    $this->repair('paragraph_webform', [
      'paragraph_webform_target_id' => fn (?string $value): bool => $value === NULL || $value === '' || isset($webform_ids[$value]),
      'paragraph_webform_status' => fn (?string $value): bool => $value === NULL || $value === '' || isset($statuses[$value]),
    ]);
  }

  /**
   * Repairs Webtools map JSON blobs translated into non-parsing prose.
   */
  protected function repairJson(): void {
    $this->repair('field_json', [
      'field_json_value' => fn (?string $value): bool => $value === NULL || trim($value) === '' || json_validate($value),
    ]);
  }

  /**
   * Restores corrupted columns of one paragraph field.
   *
   * @param string $field_name
   *   The field machine name.
   * @param array $validators
   *   A value validator per column, keyed by column name.
   */
  protected function repair(string $field_name, array $validators): void {
    $revision_table = 'paragraph_revision__' . $field_name;
    $current_table = 'paragraph__' . $field_name;
    $columns = \array_keys($validators);

    $rows = $this->database->select($revision_table, 't')
      ->fields('t', \array_merge(['entity_id', 'revision_id', 'delta', 'langcode'], $columns))
      ->orderBy('entity_id')
      ->orderBy('delta')
      ->orderBy('revision_id')
      ->orderBy('langcode')
      ->execute()
      ->fetchAll();

    // Group the history per paragraph and field delta. Translatable fields have
    // one row per language, so a group can interleave languages by revision.
    $groups = [];
    foreach ($rows as $row) {
      $groups[$row->entity_id][$row->delta][] = $row;
    }

    // For each corrupted row, collect the columns to restore and the value each
    // should take: the most recent valid value up to that revision, or the
    // first valid one when the corruption predates any valid value. Keyed by
    // "revision_id:delta:langcode" then by column.
    $restore = [];
    $paragraphs = [];
    foreach ($groups as $entity_id => $deltas) {
      foreach ($deltas as $items) {
        $first_valid = $this->firstValidValues($items, $validators);
        $last_valid = [];
        foreach ($items as $item) {
          $key = $item->revision_id . ':' . $item->delta . ':' . $item->langcode;
          foreach ($validators as $column => $is_valid) {
            if ($is_valid($item->{$column})) {
              $last_valid[$column] = $item->{$column};
              continue;
            }
            $value = $last_valid[$column] ?? $first_valid[$column] ?? NULL;
            if ($value !== NULL) {
              $restore[$key][$column] = $value;
              $paragraphs[$entity_id] = TRUE;
            }
          }
        }
      }
    }

    // Apply to the history and to the default-revision rows that render.
    $revisions = $this->applyRestore($revision_table, $restore);
    $current = $this->applyRestore($current_table, $restore);

    Drush::logger()->notice(sprintf(
      '%s: repaired %d revision row(s) and %d current row(s) across %d paragraph(s).',
      $field_name,
      $revisions,
      $current,
      count($paragraphs),
    ));
  }

  /**
   * Returns the first valid value of each column in a paragraph's history.
   *
   * @param object[] $items
   *   The field rows of one paragraph and delta, ordered by revision.
   * @param array $validators
   *   A value validator per column, keyed by column name.
   *
   * @return array
   *   The first valid value found per column, keyed by column name.
   */
  protected function firstValidValues(array $items, array $validators): array {
    $first_valid = [];
    foreach ($validators as $column => $is_valid) {
      foreach ($items as $item) {
        if ($is_valid($item->{$column})) {
          $first_valid[$column] = $item->{$column};
          break;
        }
      }
    }
    return $first_valid;
  }

  /**
   * Applies restore values to one field table.
   *
   * @param string $table
   *   The field data or revision table to fix.
   * @param array $restore
   *   The values to restore, keyed by "revision_id:delta:langcode" then column.
   *
   * @return int
   *   The number of rows updated.
   */
  protected function applyRestore(string $table, array $restore): int {
    $updated = 0;
    foreach ($restore as $key => $fields) {
      [$revision_id, $delta, $langcode] = explode(':', $key);
      $updated += $this->database->update($table)
        ->fields($fields)
        ->condition('revision_id', $revision_id)
        ->condition('delta', $delta)
        ->condition('langcode', $langcode)
        ->execute();
    }
    return $updated;
  }

}
