<?php

/**
 * @file
 * Deploy hooks for the Assessments module.
 */

declare(strict_types=1);

use Drupal\assessments\AssessmentInterface;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Deduplicate shared assessment references in binding requirements.
 */
function assessments_deploy_201200(): string {
  $deep_copy_fields = ['expression', 'affects', 'has_participation'];
  $messages = [];
  $entity_type_manager = \Drupal::entityTypeManager();
  $assessment_storage = $entity_type_manager->getStorage('assessment');
  $paragraph_storage = $entity_type_manager->getStorage('paragraph');
  $database = \Drupal::database();

  // Load all assessment reports.
  $report_ids = $assessment_storage->getQuery()
    ->accessCheck(FALSE)
    ->condition('type', 'report')
    ->execute();

  if (empty($report_ids)) {
    return 'No assessment reports found.';
  }

  $reports = $assessment_storage->loadMultiple($report_ids);
  $total_duplicated = 0;

  // Collect all field updates needed before touching the database.
  // Keys: paragraph_id → field_name → delta → new_target_id.
  $field_updates = [];
  // Keep paragraph objects alive for revision IDs.
  $paragraph_objects = [];

  foreach ($reports as $report) {
    \assert($report instanceof AssessmentInterface);
    if (!$report->hasField('binding_requirement')) {
      continue;
    }

    // Collect all paragraph IDs referenced by this report.
    $paragraph_ids = array_column(
      $report->get('binding_requirement')->getValue(),
      'target_id',
    );
    if (empty($paragraph_ids)) {
      continue;
    }

    $paragraphs = $paragraph_storage->loadMultiple($paragraph_ids);

    // Track which assessment IDs have already been seen per field within
    // this report. The first paragraph that references an assessment keeps
    // it; subsequent paragraphs get a duplicate.
    $seen = [];

    foreach ($paragraphs as $paragraph) {
      \assert($paragraph instanceof ParagraphInterface);
      if ($paragraph->bundle() !== 'binding_requirement') {
        continue;
      }

      foreach ($deep_copy_fields as $field_name) {
        if (!$paragraph->hasField($field_name)) {
          continue;
        }

        foreach ($paragraph->get($field_name) as $delta => $item) {
          if (!$item->entity instanceof AssessmentInterface) {
            continue;
          }

          $ref_id = (string) $item->entity->id();
          $key = $field_name . ':' . $ref_id;

          if (isset($seen[$key])) {
            // This reference is shared — duplicate it.
            $duplicate = $item->entity->createDuplicate();
            $duplicate->save();
            $field_updates[$paragraph->id()][$field_name][$delta] = $duplicate->id();
            $paragraph_objects[$paragraph->id()] = $paragraph;
            $total_duplicated++;
            $messages[] = sprintf(
              'Report %s: paragraph %s field "%s" delta %d — duplicated assessment %s as %s.',
              $report->id(),
              $paragraph->id(),
              $field_name,
              $delta,
              $ref_id,
              $duplicate->id(),
            );
          }
          else {
            $seen[$key] = TRUE;
          }
        }
      }
    }
  }

  // Apply field updates via direct DB writes to bypass
  // BindingRequirementParagraph::postSave → onUpdateEntityReferenceFieldsOwned,
  // which would otherwise delete assessments that are still referenced by other
  // paragraphs in the same report.
  foreach ($field_updates as $paragraph_id => $fields) {
    $revision_id = $paragraph_objects[$paragraph_id]->getRevisionId();
    foreach ($fields as $field_name => $deltas) {
      $data_table = "paragraph__{$field_name}";
      $revision_table = "paragraph_revision__{$field_name}";
      $column = "{$field_name}_target_id";

      foreach ($deltas as $delta => $new_target_id) {
        $database->update($data_table)
          ->fields([$column => $new_target_id])
          ->condition('entity_id', $paragraph_id)
          ->condition('delta', $delta)
          ->execute();

        if ($database->schema()->tableExists($revision_table)) {
          $database->update($revision_table)
            ->fields([$column => $new_target_id])
            ->condition('entity_id', $paragraph_id)
            ->condition('revision_id', $revision_id)
            ->condition('delta', $delta)
            ->execute();
        }
      }
    }

    $messages[] = sprintf('Paragraph %s: field references updated directly.', $paragraph_id);
  }

  // Clear entity cache for modified paragraphs.
  if (!empty($paragraph_objects)) {
    $paragraph_storage->resetCache(array_keys($paragraph_objects));
  }

  $messages[] = sprintf(
    'Total shared references duplicated: %d.',
    $total_duplicated,
  );
  return implode("\n", $messages);
}
