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

use Drupal\paragraphs\Entity\Paragraph;
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

/**
 * Transform document body text field to paragraphs.
 */
function joinup_core_deploy_110501(array &$sandbox): string {
  // We update about 4900 document nodes. The operation takes 240 seconds
  // (4 minutes) in total to finish. Updating the nodes takes 130 seconds, the
  // remaining time (110 seconds) is spent on reindexing.
  return _joinup_core_transform_node_body_to_paragraphs($sandbox, 'document');
}

/**
 * Transforms the text field of a node type to paragraphs reference.
 *
 * Transformation needs quite lot amount of memory (peak usage is about 234 MB
 * at the end of updating document nodes). I tried to drop entity storage caches
 * of nodes and paragraphs, but it didn't help – and I didn't dig deeper. Hence,
 * this is a reusable helper to perform the updates.
 *
 * @param array $sandbox
 *   The sandbox for batching.
 * @param string $nodeType
 *   The node type.
 * @param string $sourceField
 *   Machine name of the source field (with "text", "string", or equivalent
 *   type).
 * @param string $destinationField
 *   The machine name of the destination entity reference revisions field, with
 *   "paragraph" target type.
 *
 * @return string
 *   Message to output between iteration and at the end of the process.
 *
 * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
 * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
 * @throws \Drupal\Core\Entity\EntityStorageException
 */
function _joinup_core_transform_node_body_to_paragraphs(
  array &$sandbox,
  string $nodeType,
  string $sourceField = 'body',
  string $destinationField = 'field_paragraphs_body'
): string {
  $nodeStorage = \Drupal::entityTypeManager()->getStorage('node');
  if (!isset($sandbox['nids'])) {
    $sandbox['nids'] = $nodeStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', $nodeType)
      ->execute();
    $sandbox['count'] = 0;
    $sandbox['max'] = count($sandbox['nids']);
  }

  $nids = array_splice($sandbox['nids'], 0, 100);
  foreach ($nodeStorage->loadMultiple($nids) as $node) {
    $paragraph = Paragraph::create([
      'type' => 'simple_paragraph',
      'field_body' => $node->get($sourceField)->getValue(),
    ]);
    $node->set($destinationField, ['entity' => $paragraph]);
    // Skip notifications created and sent by joinup_notification module.
    $node->skip_notification = TRUE;
    $node->save();

    $sandbox['count']++;
  }

  $sandbox['#finished'] = (int) empty($sandbox['nids']);
  return "Processed {$sandbox['count']} items out of {$sandbox['max']}.";
}
