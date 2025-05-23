<?php

/**
 * @file
 * Deploy hooks for Joinup Assessment module.
 */

declare(strict_types=1);

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\file\FileInterface;
use Joinup\EnvironmentDetector;

/**
 * Update file access for published assessment reports.
 */
function joinup_assessment_deploy_200500(array &$sandbox): string {
  $fields = ['binding_requirement_documents', 'upload_assessment_documents'];

  $entityTypeManager = \Drupal::entityTypeManager();
  $submissionStorage = $entityTypeManager->getStorage('webform_submission');
  $fileStorage = $entityTypeManager->getStorage('file');
  $fileSystem = \Drupal::service('file_system');
  $fileRepository = \Drupal::service('file.repository');

  if (!isset($sandbox['ids'])) {
    $nodeStorage = $entityTypeManager->getStorage('node');

    // Get all published assessments report linked webform submissions.
    $ids = $nodeStorage->getAggregateQuery()->accessCheck(FALSE)
      ->condition('type', 'assessment_report')
      ->condition('status', TRUE)
      ->groupBy('submission')
      ->execute();

    $sandbox['ids'] = [];
    foreach ($ids as $item) {
      $sandbox['ids'][] = $item['submission_target_id'];
    }

    $sandbox['progress'] = 0;
    $sandbox['total'] = count($sandbox['ids']);
  }

  $ids = array_splice($sandbox['ids'], 0, 10);
  foreach ($submissionStorage->loadMultiple($ids) as $submission) {
    foreach ($fields as $field) {
      if (!$fids = $submission->getData()[$field] ?? []) {
        continue;
      }

      foreach ($fileStorage->loadMultiple($fids) as $file) {
        assert($file instanceof FileInterface);

        $sourceUri = $file->getFileUri();
        $path = StreamWrapperManager::getTarget($sourceUri);

        // Testing support.
        if (!file_exists($sourceUri) && !EnvironmentDetector::isProduction()) {
          // In non-production environments, create a fake file if the real file
          // is missing from the file system, so that we're still able to test
          // this deployment script. It happens in all environments that are
          // running without files from production (local, testing, ACC, etc.)
          $sourceDir = $fileSystem->dirname($sourceUri);
          if (!$fileSystem->prepareDirectory($sourceDir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
            throw new \RuntimeException("Directory $sourceDir cannot be created or it's not writable.");
          }
          file_put_contents($sourceUri, 'fake');
        }

        $destinationUri = "public://$path";
        $destinationDir = $fileSystem->dirname($destinationUri);
        if (!$fileSystem->prepareDirectory($destinationDir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
          throw new \RuntimeException("Directory $destinationDir cannot be created or it's not writable.");
        }

        $fileRepository->move($file, $destinationUri);
      }
    }
  }
  $sandbox['progress'] += count($ids);

  $sandbox['#finished'] = (int) empty($sandbox['ids']);

  return "Processed {$sandbox['progress']} submissions out of {$sandbox['total']}";
}
