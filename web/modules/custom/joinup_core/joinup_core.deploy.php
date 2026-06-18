<?php

/**
 * @file
 * Deploy hooks for the Joinup core module.
 */

declare(strict_types=1);

use Drupal\meta_entity\Entity\MetaEntityInterface;

/**
 * Resets and re-queues the translations of the curated homepage topics.
 */
function joinup_core_deploy_201500(array &$sandbox): string {
  if (!function_exists('tmgmt_content_create_continuous_job_items')) {
    return 'The tmgmt_content module is not enabled; homepage topics were not re-queued.';
  }

  $queue = \Drupal::entityTypeManager()->getStorage('entity_subqueue')->load('homepage_topics');
  if (!$queue) {
    return 'The "homepage_topics" subqueue was not found; nothing to do.';
  }

  $removed = 0;
  $requeued = 0;
  $skipped = 0;
  foreach ($queue->get('items')->referencedEntities() as $entity) {
    if (!$entity instanceof MetaEntityInterface) {
      continue;
    }
    $meta_entity = $entity->getUntranslated();
    $non_english = array_diff(array_keys($meta_entity->getTranslationLanguages()), ['en']);
    if (!$non_english) {
      continue;
    }

    foreach ($non_english as $langcode) {
      $meta_entity->removeTranslation($langcode);
      $removed++;
    }
    $meta_entity->save();

    // Re-queue the English source for (re-)translation.
    tmgmt_content_create_continuous_job_items($meta_entity);
    $requeued++;
  }

  return 'Cleaned up ' . $removed . ' homepage topic translations and re-queued ' . $requeued . ' translations. ' . $skipped . ' homepage topic translations were skipped.';
}
