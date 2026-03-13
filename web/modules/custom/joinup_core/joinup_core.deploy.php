<?php

/**
 * @file
 * Joinup Core deployment hooks.
 */

declare(strict_types=1);

use Drupal\Core\File\FileExists;

/**
 * Implements hook_deploy_NAME_invoke().
 */
function joinup_core_deploy_201100(): void {
  $entity_type_manager = \Drupal::entityTypeManager();
  $paragraph_storage = $entity_type_manager->getStorage('paragraph');
  $assessment_storage = $entity_type_manager->getStorage('assessment');
  $file_system = \Drupal::service('file_system');
  $file_usage = \Drupal::service('file.usage');
  $logger = \Drupal::logger('joinup_core');

  $processed_files = [];
  $assessment_query = $assessment_storage->getQuery()
    ->accessCheck(FALSE)
    ->condition('type', 'report')
    ->exists('documents');

  $assessment_ids = $assessment_query->execute();
  foreach ($assessment_storage->loadMultiple($assessment_ids) as $assessment) {
    $file_list = $assessment->get('documents');
    $changed = FALSE;

    foreach ($file_list->referencedEntities() as $delta => $file) {
      $file_id = $file->id();
      if (isset($processed_files[$file_id])) {
        $new_file = $file->createDuplicate();
        $original_uri = $file->getFileUri();
        $path_parts = pathinfo($original_uri);
        $new_uri = $path_parts['dirname'] . '/' . $path_parts['filename'] . '.' . $path_parts['extension'];

        try {
          $new_uri = $file_system->copy($original_uri, $new_uri, FileExists::Rename);
          $new_file->setFileUri($new_uri);
          $new_file->save();

          $file_list->set($delta, $new_file);
          $file_usage->add($new_file, 'joinup_core', 'assessment', $assessment->id());
          $changed = TRUE;

          $logger->info('Duplicated file with ID @id for assessment @assessment.', [
            '@id' => $file_id,
            '@assessment' => $assessment->id(),
          ]);
        }
        catch (\Exception $e) {
          $logger->error('Failed to duplicate file with ID @id for assessment @assessment: @message', [
            '@id' => $file_id,
            '@assessment' => $assessment->id(),
            '@message' => $e->getMessage(),
          ]);
        }
      }
      else {
        $processed_files[$file_id] = $file_id;
      }
    }

    if ($changed) {
      $assessment->save();
    }
  }
  $assessment_storage->resetCache($assessment_ids);

  $paragraph_query = $paragraph_storage->getQuery()
    ->accessCheck(FALSE)
    ->condition('type', 'binding_requirement')
    ->exists('requirement_documents');

  $paragraph_ids = $paragraph_query->execute();
  /** @var \Drupal\paragraphs\ParagraphInterface $paragraph */
  foreach ($paragraph_storage->loadMultiple($paragraph_ids) as $paragraph) {
    $file_list = $paragraph->get('requirement_documents');
    $changed = FALSE;

    foreach ($file_list->referencedEntities() as $delta => $file) {
      $file_id = $file->id();
      if (isset($processed_files[$file_id])) {
        $new_file = $file->createDuplicate();
        $original_uri = $file->getFileUri();
        $path_parts = pathinfo($original_uri);
        $new_uri = $path_parts['dirname'] . '/' . $path_parts['filename'] . '.' . $path_parts['extension'];

        try {
          $new_uri = $file_system->copy($original_uri, $new_uri, FileExists::Rename);
          $new_file->setFileUri($new_uri);
          $new_file->save();

          $file_list->set($delta, $new_file);
          $file_usage->add($new_file, 'joinup_core', 'paragraph', $paragraph->id());
          $changed = TRUE;

          $logger->info('Duplicated file with ID @id for paragraph @paragraph.', [
            '@id' => $file_id,
            '@paragraph' => $paragraph->id(),
          ]);
        }
        catch (\Exception $e) {
          $logger->error('Failed to duplicate file with ID @id for paragraph @paragraph: @message', [
            '@id' => $file_id,
            '@paragraph' => $paragraph->id(),
            '@message' => $e->getMessage(),
          ]);
        }
      }
      else {
        $processed_files[$file_id] = $file_id;
      }
    }

    if ($changed) {
      $paragraph->save();
    }
  }
  $paragraph_storage->resetCache($paragraph_ids);
}
