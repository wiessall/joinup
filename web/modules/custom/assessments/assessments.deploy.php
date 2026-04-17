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
    $report_changed = FALSE;

    foreach ($paragraphs as $paragraph) {
      \assert($paragraph instanceof ParagraphInterface);
      if ($paragraph->bundle() !== 'binding_requirement') {
        continue;
      }

      $paragraph_changed = FALSE;

      foreach ($deep_copy_fields as $field_name) {
        if (!$paragraph->hasField($field_name)) {
          continue;
        }

        foreach ($paragraph->get($field_name) as $item) {
          if (!$item->entity instanceof AssessmentInterface) {
            continue;
          }

          $ref_id = (string) $item->entity->id();
          $key = $field_name . ':' . $ref_id;

          if (isset($seen[$key])) {
            // This reference is shared — duplicate it.
            $duplicate = $item->entity->createDuplicate();
            $duplicate->save();
            $item->entity = $duplicate;
            $paragraph_changed = TRUE;
            $total_duplicated++;
            $messages[] = sprintf(
              'Report %s: paragraph %s field "%s" — duplicated assessment %s as %s.',
              $report->id(),
              $paragraph->id(),
              $field_name,
              $ref_id,
              $duplicate->id(),
            );
          }
          else {
            $seen[$key] = TRUE;
          }
        }
      }

      if ($paragraph_changed) {
        $paragraph->save();
        $report_changed = TRUE;
      }
    }

    if ($report_changed) {
      $messages[] = sprintf(
        'Report %s: saved with updated references.',
        $report->id(),
      );
    }
  }

  $messages[] = sprintf(
    'Total shared references duplicated: %d.',
    $total_duplicated,
  );
  return implode("\n", $messages);
}
