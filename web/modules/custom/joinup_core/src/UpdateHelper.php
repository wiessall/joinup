<?php

declare(strict_types=1);

namespace Drupal\joinup_core;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\Php;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Update helper for Joinup.
 */
class UpdateHelper implements ContainerInjectionInterface {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected TimeInterface $time,
    protected Connection $db,
    protected Php $uuid,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('datetime.time'),
      $container->get('database'),
      $container->get('uuid'),
    );
  }

  /**
   * Transforms the text field of a node type to paragraphs reference.
   *
   * Transformation needs quite lot amount of memory (peak usage is about 234 MB
   * at the end of updating document nodes). I tried to drop entity storage
   * caches of nodes and paragraphs, but it didn't help – and I didn't dig
   * deeper. Hence, this is a reusable helper to perform the updates.
   *
   * @param array $sandbox
   *   The sandbox for batching.
   * @param string[] $nodeTypes
   *   List of node types.
   * @param string $sourceField
   *   Machine name of the source field (with "text", "string", or equivalent
   *   type).
   * @param string $destinationField
   *   The machine name of the destination entity reference revisions field,
   *   with "paragraph" target type.
   *
   * @return string
   *   Progress status message.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function transformNodeBodyToLayoutParagraphs(
    array &$sandbox,
    array $nodeTypes,
    string $sourceField = 'body',
    string $destinationField = 'field_paragraphs_body',
  ): string {
    $nodeStorage = $this->entityTypeManager->getStorage('node');

    if (!isset($sandbox['nids'])) {
      $sandbox['nids'] = $nodeStorage->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', $nodeTypes, 'IN')
        ->execute();
      $sandbox['max'] = count($sandbox['nids']);
      $sandbox['progress'] = 0;
    }

    $nids = array_splice($sandbox['nids'], 0, 100);
    foreach ($nodeStorage->loadMultiple($nids) as $node) {
      $sandbox['progress']++;
      assert($node instanceof NodeInterface);
      if ($node->get($sourceField)->isEmpty()) {
        continue;
      }
      $ids = $this->updateBaseTables();
      $this->updateSharedTables($ids, $node, $destinationField);
      $this->updateParagraphDedicatedTable($ids, $node, $sourceField);
      $this->updateNodeDedicatedTables($ids, $node, $destinationField);
    }

    $sandbox['#finished'] = (int) empty($sandbox['nids']);
    return "Processed {$sandbox['progress']} out of {$sandbox['max']} nodes";
  }

  /**
   * Updates the base tables of the paragraphs.
   */
  private function updateBaseTables(): array {
    $layoutId = $this->db->insert('paragraphs_item')
      ->fields(['type', 'uuid', 'langcode'])
      ->values([
        'type' => 'layout',
        'uuid' => $layoutUuid = $this->uuid->generate(),
        'langcode' => 'en',
      ])->execute();
    $layoutRevisionId = $this->db->insert('paragraphs_item_revision')
      ->fields(['id', 'langcode', 'revision_default'])
      ->values([
        'id' => $layoutId,
        'langcode' => 'en',
        'revision_default' => 1,
      ])->execute();
    $paragraphId = $this->db->insert('paragraphs_item')
      ->fields(['type', 'uuid', 'langcode'])
      ->values([
        'type' => 'simple_paragraph',
        'uuid' => $this->uuid->generate(),
        'langcode' => 'en',
      ])->execute();
    $paragraphRevisionId = $this->db->insert('paragraphs_item_revision')
      ->fields(['id', 'langcode', 'revision_default'])
      ->values([
        'id' => $paragraphId,
        'langcode' => 'en',
        'revision_default' => 1,
      ])->execute();
    $this->db->update('paragraphs_item')
      ->fields(['revision_id' => $layoutRevisionId])
      ->condition('id', $layoutId)
      ->execute();
    $this->db->update('paragraphs_item')
      ->fields(['revision_id' => $paragraphRevisionId])
      ->condition('id', $paragraphId)
      ->execute();

    return [
      'layoutId' => $layoutId,
      'layoutUuid' => $layoutUuid,
      'layoutRevisionId' => $layoutRevisionId,
      'paragraphId' => $paragraphId,
      'paragraphRevisionId' => $paragraphRevisionId,
    ];
  }

  /**
   * Updates the data tables of the paragraphs.
   */
  private function updateSharedTables(array $ids, NodeInterface $node, string $destinationField): void {
    $now = $this->time->getRequestTime();

    $layoutValues = [
      'id' => $ids['layoutId'],
      'revision_id' => $ids['layoutRevisionId'],
      'type' => 'layout',
      'langcode' => 'en',
      'status' => 1,
      'created' => $now,
      'parent_id' => $node->id(),
      'parent_type' => 'node',
      'parent_field_name' => $destinationField,
      'behavior_settings' => serialize([
        'layout_paragraphs' => [
          'layout' => 'layout_onecol',
          'config' => ['label' => ''],
          'parent_uuid' => NULL,
          'region' => NULL,
        ],
      ]),
      'default_langcode' => 1,
      'revision_translation_affected' => 1,
    ];
    $paragraphValues = [
      'id' => $ids['paragraphId'],
      'revision_id' => $ids['paragraphRevisionId'],
      'type' => 'simple_paragraph',
      'langcode' => 'en',
      'status' => 1,
      'created' => $now,
      'parent_id' => $node->id(),
      'parent_type' => 'node',
      'parent_field_name' => $destinationField,
      'behavior_settings' => serialize([
        'layout_paragraphs' => [
          'parent_uuid' => $ids['layoutUuid'],
          'region' => 'content',
        ],
      ]),
      'default_langcode' => 1,
      'revision_translation_affected' => 1,
    ];
    foreach ([
      'paragraphs_item_field_data',
      'paragraphs_item_revision_field_data',
    ] as $table) {
      if ($table === 'paragraphs_item_revision_field_data') {
        unset($layoutValues['type']);
        unset($paragraphValues['type']);
      }
      $this->db->insert($table)
        ->fields(array_keys($layoutValues))
        ->values($layoutValues)
        ->values($paragraphValues)
        ->execute();
    }
  }

  /**
   * Updates the field dedicated tables of the paragraphs.
   */
  private function updateParagraphDedicatedTable(array $ids, NodeInterface $node, string $sourceField): void {
    $values = [
      'bundle' => 'simple_paragraph',
      'entity_id' => $ids['paragraphId'],
      'revision_id' => $ids['paragraphRevisionId'],
      'langcode' => 'en',
      'delta' => 0,
      'field_body_value' => $node->get($sourceField)->value,
      'field_body_format' => 'content_editor',
    ];
    foreach (['paragraph', 'paragraph_revision'] as $type) {
      $this->db->insert("{$type}__field_body")
        ->fields(array_keys($values))
        ->values($values)
        ->execute();
    }
  }

  /**
   * Create the connection between paragraphs and nodes.
   */
  private function updateNodeDedicatedTables(array $ids, NodeInterface $node, string $destinationField): void {
    $layoutValues = [
      'bundle' => $node->bundle(),
      'entity_id' => $node->id(),
      'revision_id' => $node->getRevisionId(),
      'langcode' => 'en',
      'delta' => 0,
      'field_paragraphs_body_target_id' => $ids['layoutId'],
      'field_paragraphs_body_target_revision_id' => $ids['layoutRevisionId'],
    ];
    $paragraphValues = [
      'bundle' => $node->bundle(),
      'entity_id' => $node->id(),
      'revision_id' => $node->getRevisionId(),
      'langcode' => 'en',
      'delta' => 1,
      'field_paragraphs_body_target_id' => $ids['paragraphId'],
      'field_paragraphs_body_target_revision_id' => $ids['paragraphRevisionId'],
    ];
    foreach (['node', 'node_revision'] as $type) {
      $this->db->insert("{$type}__$destinationField")
        ->fields(array_keys($layoutValues))
        ->values($layoutValues)
        ->values($paragraphValues)
        ->execute();
    }
  }

}
