<?php

/**
 * @file
 * Deploy functions for Joinup.
 *
 * This should only contain update functions that rely on the Drupal API and
 * need to run _after_ the configuration is imported.
 *
 * This is applicable in most cases. However, in case the update code enables
 * some functionality that is required for configuration to be successfully
 * imported, it should instead be placed in joinup_core.post_update.php.
 */

declare(strict_types=1);

use Drupal\search_api\Entity\Index;

/**
 * Refresh legacy content to rectify Topic term name.
 */
function joinup_core_deploy_110500(): void {
  $content = [
    'node' => [
      // /collection/semic-support-centre/document/guidelines-use-code-list
      701218,
    ],
    'rdf_entity' => [
      // /collection/interoperability-maturity-tools-imts-digital-public-services
      'http://data.europa.eu/w21/abe7d0f7-8c41-465b-86c0-93d2f857f998',
      // /collection/eparticipation-and-evoting/solution/eusurvey/release-9
      'http://data.europa.eu/w21/6d664eef-d0d4-4ad0-bfb4-aacb83856529',
      // /collection/eparticipation-and-evoting/solution/eusurvey/release-8
      'http://data.europa.eu/w21/0434c7f8-6614-46c4-8f23-7d8385172dfb',
      // /collection/gnu-health/solution/gnu-health-hospital-information-system/release/170
      'http://data.europa.eu/w21/c580493a-554b-4f53-887b-7988ce5c8fd9',
    ],
  ];
  $items = [];
  /** @var \Drupal\search_api\IndexInterface $index */
  $index = Index::load('published');
  foreach ($content as $type => $ids) {
    foreach ($ids as $id) {
      $item_id = 'entity:' . $type . '/' . $id . ':en';
      $items[$item_id] = $index->loadItem($item_id);
    }
  }
  $index->indexSpecificItems($items);
}
