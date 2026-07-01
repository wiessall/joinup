<?php

/**
 * @file
 * Deploy hooks for the Joinup core module.
 */

declare(strict_types=1);

use Drupal\meta_entity\Entity\MetaEntityInterface;

/**
 * Resets, re-aliases and re-queues the curated homepage topic translations.
 */
function joinup_core_deploy_201500(array &$sandbox): string {
  $queue = \Drupal::entityTypeManager()->getStorage('entity_subqueue')->load('homepage_topics');
  $alias_storage = \Drupal::entityTypeManager()->getStorage('path_alias');
  $pathauto_generator = \Drupal::hasService('pathauto.generator') ? \Drupal::service('pathauto.generator') : NULL;

  $removed = 0;
  $requeued = 0;
  $realiased = 0;
  foreach ($queue->get('items')->referencedEntities() as $entity) {
    if (!$entity instanceof MetaEntityInterface) {
      continue;
    }
    $meta_entity = $entity->getUntranslated();

    // Drop every non-English translation.
    $non_english = array_diff(array_keys($meta_entity->getTranslationLanguages()), ['en']);
    if ($non_english) {
      foreach ($non_english as $langcode) {
        $meta_entity->removeTranslation($langcode);
        $removed++;
      }
      $meta_entity->save();
    }

    // Replace the shared language-neutral alias with a per-language one.
    if ($pathauto_generator) {
      $aliases = $alias_storage->loadByProperties([
        'path' => '/' . $meta_entity->toUrl('canonical')->getInternalPath(),
      ]);
      if ($aliases) {
        $alias_storage->delete($aliases);
      }
      foreach (array_keys($meta_entity->getTranslationLanguages()) as $langcode) {
        $pathauto_generator->updateEntityAlias($meta_entity->getTranslation($langcode), 'update');
      }
      $realiased++;
    }

    // Re-queue the English source for (re-)translation.
    tmgmt_content_create_continuous_job_items($meta_entity);
    $requeued++;
  }

  return sprintf('Removed %d non-English translation(s), regenerated aliases for %d homepage topic(s) and re-queued %d for translation.', $removed, $realiased, $requeued);
}

/**
 * Restore webform paragraph references that tmgmt mangled into invalid IDs.
 */
function joinup_core_deploy_201501(): string {
  $database = \Drupal::database();
  $entity_type_manager = \Drupal::entityTypeManager();
  $storage = $entity_type_manager->getStorage('paragraph');
  $restored = 0;

  // A webform reference target_id is a machine name; anything that is not an
  // existing webform ID is a value a translation mangled.
  $webform_ids = $entity_type_manager->getStorage('webform')->getQuery()->accessCheck(FALSE)->execute();
  $valid = array_flip($webform_ids);

  $ids = $storage->getQuery()->accessCheck(FALSE)->exists('paragraph_webform')->execute();
  foreach ($storage->loadMultiple($ids) as $paragraph) {
    if (isset($valid[$paragraph->get('paragraph_webform')->target_id])) {
      continue;
    }

    // Walk the revision history for the most recent valid webform ID.
    $history = $database->select('paragraph_revision__paragraph_webform', 't')
      ->fields('t', ['paragraph_webform_target_id'])
      ->condition('entity_id', $paragraph->id())
      ->orderBy('revision_id', 'DESC')
      ->execute()
      ->fetchCol();
    foreach ($history as $value) {
      if (isset($valid[$value])) {
        // Set only the target_id to keep the item's other columns intact, and
        // update the current revision in place so the referencing node sees it.
        $paragraph->get('paragraph_webform')->target_id = $value;
        $paragraph->setNewRevision(FALSE);
        $paragraph->save();
        $restored++;
        break;
      }
    }
  }

  return sprintf('Restored %d webform reference(s).', $restored);
}
