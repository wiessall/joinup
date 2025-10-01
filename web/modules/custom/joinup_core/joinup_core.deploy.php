<?php

/**
 * @file
 * Joinup Core deployment hooks.
 */

declare(strict_types=1);

/**
 * Fix webform configuration validation errors and clean control characters.
 */
function joinup_core_deploy_200700(): string {
  $webform_config = \Drupal::configFactory()
    ->getEditable('webform.webform.general_criteria_for_interoperab');

  if ($webform_config->isNew()) {
    return 'Webform configuration not found.';
  }

  $all_data = $webform_config->get();
  $changes_made = [];

  // Remove all problematic attribute sections that cause ValidKeys issues.
  $sections_to_remove = [];
  if (isset($all_data['settings']) && is_array($all_data['settings'])) {
    foreach ($all_data['settings'] as $key => $value) {
      if (str_ends_with($key, '_attributes') && is_array($value) && empty($value)) {
        $sections_to_remove[] = "settings.{$key}";
      }
    }
  }

  foreach ($sections_to_remove as $path) {
    $parts = explode('.', $path);
    $current = &$all_data;
    $found = TRUE;

    for ($i = 0; $i < count($parts) - 1; $i++) {
      if (!isset($current[$parts[$i]])) {
        $found = FALSE;
        break;
      }
      $current = &$current[$parts[$i]];
    }

    if ($found && isset($current[$parts[count($parts) - 1]])) {
      unset($current[$parts[count($parts) - 1]]);
      $changes_made[] = "removed $path";
    }
  }

  // Function to recursively clean control characters from strings.
  $clean_control_chars = function ($value) use (&$clean_control_chars) {
    if (is_string($value)) {
      $original = $value;
      // Remove ZWSP (U+200B), ZWNJ (U+200C), ZWJ (U+200D), BOM (U+FEFF).
      $value = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $value);
      // Remove other control characters except
      // newlines, tabs, and carriage returns.
      $value = preg_replace('/[\x{00}-\x{08}\x{0B}\x{0C}\x{0E}-\x{1F}\x{7F}-\x{9F}]/u', '', $value);
      return ['value' => $value, 'changed' => ($original !== $value)];
    }
    elseif (is_array($value)) {
      $changed = FALSE;
      foreach ($value as &$item) {
        $result = $clean_control_chars($item);
        $item = $result['value'];
        if ($result['changed']) {
          $changed = TRUE;
        }
      }
      return ['value' => $value, 'changed' => $changed];
    }
    return ['value' => $value, 'changed' => FALSE];
  };

  $result = $clean_control_chars($all_data);
  if ($result['changed']) {
    $all_data = $result['value'];
    $changes_made[] = 'removed control characters';
  }

  if ($changes_made) {
    $webform_config->setData($all_data);
    $webform_config->save();
    return 'Fixed webform configuration: ' . implode(', ', $changes_made) . '.';
  }

  return 'Webform configuration already clean.';
}

/**
 * Clean up leftover queue item.
 */
function joinup_core_deploy_200701(): string {
  $item = new stdClass();
  $item->item_id = '190201475';
  \Drupal::service('queue')->get('eu_oss_catalogue')->deleteItem($item);
  return 'Deleted leftover queue item 190201475 from eu_oss_catalogue queue.';
}

/**
 * Disable "Show solutions related by EIRA terms" for pre-wizard solutions.
 */
function joinup_core_deploy_200702(array &$sandbox): string {
  // Get all solution nodes created before July 14, 2025.
  // This is when the Solution Wizard was implemented.
  $cutoff_date = strtotime('2025-07-14 00:00:00 UTC');

  if (!isset($sandbox['progress'])) {
    $nids = \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('type', 'solution')
      ->condition('created', $cutoff_date, '<')
      ->execute();

    if (empty($nids)) {
      $sandbox['#finished'] = 1;
      return 'No solutions found created before July 14, 2025.';
    }

    $sandbox['nids'] = array_values($nids);
    $sandbox['total'] = count($nids);
    $sandbox['progress'] = 0;
    $sandbox['updated_count'] = 0;
  }

  $batch_size = 50;
  $meta_entity_repository = \Drupal::getContainer()->get('meta_entity.repository');
  $node_storage = \Drupal::entityTypeManager()->getStorage('node');

  $nids_to_process = array_slice($sandbox['nids'], $sandbox['progress'], $batch_size);

  foreach ($nids_to_process as $nid) {
    $solution = $node_storage->load($nid);
    if (!$solution) {
      $sandbox['progress']++;
      continue;
    }

    $meta_entity = $meta_entity_repository->getMetaEntityForEntity($solution, 'solution_settings');

    if ($meta_entity && $meta_entity->hasField('list_related_entities')) {
      // Only update if the field is currently set to 1 (enabled).
      if ($meta_entity->get('list_related_entities')->value === '1') {
        $meta_entity->set('list_related_entities', '0');
        $meta_entity->save();
        $sandbox['updated_count']++;
      }
    }

    $sandbox['progress']++;
  }

  $sandbox['#finished'] = $sandbox['progress'] / $sandbox['total'];

  if ($sandbox['#finished'] >= 1) {
    return sprintf(
      'Processed %d solutions created before July 14, 2025. Disabled "Show solutions related by EIRA terms" for %d solutions.',
      $sandbox['total'],
      $sandbox['updated_count']
    );
  }

  return sprintf(
    'Processing solutions... %d/%d',
    $sandbox['progress'],
    $sandbox['total']
  );
}
