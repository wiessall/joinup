<?php

/**
 * @file
 * Joinup Core deployment hooks.
 */

declare(strict_types=1);

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeInterface;

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
function joinup_core_deploy_200702(): string {
  // Get all solution nodes created before July 14, 2025.
  // This is when the Solution Wizard was implemented.
  $cutoff_date = strtotime('2025-07-14 00:00:00 UTC');

  $nids = \Drupal::entityQuery('node')
    ->accessCheck(FALSE)
    ->condition('type', 'solution')
    ->condition('created', $cutoff_date, '<')
    ->execute();

  if (empty($nids)) {
    return 'No solutions found created before July 14, 2025.';
  }

  // Queue all solutions for processing.
  $queue = \Drupal::queue('joinup_core_disable_eira_terms');
  foreach ($nids as $nid) {
    $queue->createItem(['nid' => $nid]);
  }

  $count = count($nids);
  return sprintf(
    'Queued %d solution(s) created before July 14, 2025 for processing. The queue will be processed by cron.',
    $count
  );
}

/**
 * Delete invalid track_usage queue items.
 */
function joinup_core_deploy_200703(array &$sandbox): string {
  $db = \Drupal::database();
  if (!isset($sandbox['item_ids'])) {
    $sandbox['item_ids'] = $db->select('queue', 'q')
      ->fields('q', ['item_id'])
      ->condition('q.name', 'track_usage')
      ->execute()
      ->fetchCol();
    $sandbox['processed'] = 0;
    $sandbox['deleted'] = 0;
    $sandbox['total'] = count($sandbox['item_ids']);
    $sandbox['content_entity_type_ids'] = array_keys(
      array_filter(
        \Drupal::entityTypeManager()->getDefinitions(),
        fn (EntityTypeInterface $entityType): bool => $entityType instanceof ContentEntityTypeInterface,
      ),
    );
  }
  $itemIds = array_splice($sandbox['item_ids'], 0, 1000);
  $itemsQuery = $db->select('queue', 'q')
    ->fields('q', ['item_id', 'data'])
    ->condition('q.name', 'track_usage')
    ->condition('q.item_id', $itemIds, 'IN');
  foreach ($itemsQuery->execute()->fetchAll() as $item) {
    $unserializedData = unserialize($item->data, ['allowed_classes' => FALSE]);
    if (
      !in_array($unserializedData['type'], $sandbox['content_entity_type_ids']) ||
      !array_key_exists('revision', $unserializedData) ||
      !is_bool($unserializedData['revision']) ||
      !is_array($unserializedData['ids'])
    ) {
      $db->delete('queue')->condition('item_id', $item->item_id)->execute();
      $sandbox['deleted']++;
    }
  }
  $sandbox['processed'] += count($itemIds);
  $sandbox['#finished'] = $sandbox['processed'] / $sandbox['total'];
  return sprintf(
    "Processed %s 'track_usage' queue item from %s. Deleted %s items.",
    $sandbox['processed'],
    $sandbox['total'],
    $sandbox['deleted'],
  );
}
