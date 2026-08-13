<?php

/**
 * @file
 * Deploy hooks for the Joinup Group module.
 */

declare(strict_types=1);

/**
 * Backfills missing width/height on collection and solution logos.
 */
function joinup_group_deploy_201600(array &$sandbox): string {
  $database = \Drupal::database();

  if (!isset($sandbox['rows'])) {
    $query = $database->select('node__logo', 'l');
    $query->innerJoin('node_field_data', 'n', 'n.nid = l.entity_id AND n.langcode = l.langcode AND n.vid = l.revision_id');
    $query->fields('l', ['entity_id', 'revision_id', 'langcode', 'delta', 'logo_target_id']);
    $query->condition('l.deleted', 0);
    $query->condition('n.type', ['collection', 'solution'], 'IN');
    $query->isNotNull('l.logo_target_id');
    $or = $query->orConditionGroup()
      ->isNull('l.logo_width')
      ->isNull('l.logo_height');
    $query->condition($or);

    $sandbox['rows'] = $query->execute()->fetchAll();
    $sandbox['total'] = count($sandbox['rows']);
    $sandbox['progress'] = 0;
  }

  $row = array_shift($sandbox['rows']);
  $sandbox['progress']++;

  $file = \Drupal::entityTypeManager()->getStorage('file')->load($row->logo_target_id);
  $fixed = FALSE;
  if ($file) {
    $image = \Drupal::service('image.factory')->get($file->getFileUri());
    if ($image->isValid()) {
      $width = $image->getWidth();
      $height = $image->getHeight();

      foreach (['node__logo', 'node_revision__logo'] as $table) {
        $database->update($table)
          ->fields(['logo_width' => $width, 'logo_height' => $height])
          ->condition('entity_id', $row->entity_id)
          ->condition('revision_id', $row->revision_id)
          ->condition('langcode', $row->langcode)
          ->condition('delta', $row->delta)
          ->execute();
      }
      $fixed = TRUE;
    }
  }

  $sandbox['#finished'] = (int) empty($sandbox['rows']);

  return sprintf(
    '%s logo dimensions for node %d, %d out of %d.',
    $fixed ? 'Fixed' : 'Skipped (no valid image)',
    $row->entity_id,
    $sandbox['progress'],
    $sandbox['total']
  );
}
