<?php

/**
 * @file
 * Deploy hooks for Joinup Assessment module.
 */

declare(strict_types=1);

use Drupal\collection\Entity\CollectionInterface;
use Drupal\custom_page\Entity\CustomPageInterface;
use Drupal\joinup_assessment\AssessmentInterface;

/**
 * Update 'Assessment reports' custom page.
 */
function joinup_assessment_deploy_200100(): void {
  $layoutUuid = 'c97d143d-f012-4680-a816-dc4b2eee2ee2';
  $storage = \Drupal::entityTypeManager()->getStorage('paragraph');

  $customPage = \Drupal::getContainer()->get('entity.repository')
    ->loadEntityByUuid('node', AssessmentInterface::ASSESSMENT_REPORTS_UUID);
  if (!$customPage instanceof CustomPageInterface) {
    throw new \RuntimeException("Can't load a custom page with UUID '" . AssessmentInterface::ASSESSMENT_REPORTS_UUID . "'");
  }

  $layout = $storage->create([
    'type' => 'layout',
    'uuid' => $layoutUuid,
    'behavior_settings' => [
      'layout_paragraphs' => [
        'layout' => 'layout_onecol',
        'config' => ['label' => ''],
        'parent_uuid' => NULL,
        'region' => NULL,
      ],
      'background_theme' => [],
      'extra_class' => [],
    ],
  ]);
  $block = $storage->create([
    'type' => 'block',
    'paragraph_block' => [
      'plugin_id' => 'views_block:assessment_reports-list',
      'settings' => [
        'id' => 'views_block:assessment_reports-list',
        'label' => '',
        'label_display' => FALSE,
        'provider' => 'views',
        'views_label' => '',
        'items_per_page' => 10,
      ],
    ],
    'behavior_settings' => [
      'layout_paragraphs' => [
        'parent_uuid' => $layoutUuid,
        'region' => 'content',
      ],
    ],
  ]);

  $customPage
    ->set('title', 'Assessment reports')
    ->set('abstract', 'Interoperability assessment reports')
    ->set('field_paragraphs_body', [$layout, $block])
    ->save();
}

/**
 * Update 'Assessments' collection content listing.
 */
function joinup_assessment_deploy_200101(): void {
  $collection = \Drupal::getContainer()->get('entity.repository')
    ->loadEntityByUuid('node', AssessmentInterface::GROUP_UUID);
  if (!$collection instanceof CollectionInterface) {
    throw new \RuntimeException("Can't load a collection with UUID '" . AssessmentInterface::GROUP_UUID . "'");
  }
  $listing = $collection->get('listing_content')->getValue();
  $listing[0]['value']['query_presets'] = 'entity_bundle|assessment_report|<>';
  $collection->set('listing_content', $listing);
  $collection->skip_notification = TRUE;
  $collection->save();
}

/**
 * Update user content listing.
 */
function joinup_assessment_deploy_200102(?array &$sandbox = NULL): string {
  $db = \Drupal::database();
  if (!isset($sandbox['data'])) {
    $sandbox['data'] = $db->select('user__field_user_content', 'u')
      ->fields('u', ['entity_id', 'field_user_content_value'])
      ->execute()
      ->fetchAll(\PDO::FETCH_NUM);
    $sandbox['total'] = count($sandbox['data']);
    $sandbox['processed'] = 0;
  }

  $data = array_splice($sandbox['data'], 0, 100);
  foreach ($data as [$uid, $listing]) {
    $listing = unserialize($listing, ['allowed_classes' => FALSE]);

    // Append to potential existing value.
    $query = trim($listing['query_presets'] ?? '') . "\nentity_bundle|assessment_report|<>";
    $listing['query_presets'] = trim($query);

    $db->update('user__field_user_content')
      ->fields(['field_user_content_value' => serialize($listing)])
      ->condition('entity_id', $uid)
      ->execute();
    $sandbox['processed']++;
  };

  $sandbox['#finished'] = (int) empty($sandbox['data']);
  return "{$sandbox['processed']} out of {$sandbox['total']} users processed";
}
