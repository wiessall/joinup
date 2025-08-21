<?php

declare(strict_types=1);

namespace Drupal\joinup_paragraphs\Update;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\KeyValueStore\KeyValueFactory;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Service for migrating field data between different field types.
 */
final class MigrateFileUrl implements ContainerInjectionInterface {

  use AutowireTrait;

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire('config.storage.sync')]
    protected readonly StorageInterface $syncStorage,
    protected readonly Connection $database,
    #[Autowire('keyvalue')]
    protected readonly KeyValueFactory $keyValue,
  ) {
  }

  /**
   * Main entrypoint.
   */
  public function run(): void {
    $this->prepareParagraphs();
    $this->prepareNodeFields();
    $this->migrateNodeFileUrlField('field_file', 'resources');
    $this->migrateNodeFileUrlField('access_url', 'resources');
    $this->migrateNodeFileUrlField('documentation', 'resources');
  }

  /**
   * Prepare paragraph's structure.
   */
  private function prepareParagraphs(): void {
    $config_entities = [
      'paragraphs_type' => [
        'paragraphs.paragraphs_type.resource_file',
        'paragraphs.paragraphs_type.resource_link',
      ],
      'field_storage_config' => [
        'field.storage.paragraph.resource_file',
        'field.storage.paragraph.remote_file_url',
      ],
      'field_config' => [
        'field.field.paragraph.resource_file.resource_file',
        'field.field.paragraph.resource_link.remote_file_url',
      ],
    ];

    $this->createConfigEntities($config_entities);
  }

  /**
   * Prepare node's structure.
   */
  private function prepareNodeFields(): void {
    $config_entities = [
      'field_storage_config' => [
        'field.storage.node.resources',
      ],
      'field_config' => [
        'field.field.node.document.resources',
        'field.field.node.distribution.resources',
        'field.field.node.solution.resources',
      ],
    ];

    $this->createConfigEntities($config_entities);
  }

  /**
   * Creates configuration entities from sync storage.
   *
   * @param array $config_entities
   *   Array of configuration entities keyed by storage type.
   */
  private function createConfigEntities(array $config_entities): void {
    foreach ($config_entities as $storage => $keys) {
      foreach ($keys as $key) {
        $this->entityTypeManager->getStorage($storage)
          ->create($this->syncStorage->read($key))
          ->save();
      }
    }
  }

  /**
   * Migrates a node file_url field to paragraphs field.
   */
  private function migrateNodeFileUrlField(string $src_field, string $dest_field): void {
    // Process all revision table records.
    [, $src_revision_table] = array_keys($this->getFieldStorageSchema('node', $src_field));
    $result = $this->database->select($src_revision_table)
      ->fields($src_revision_table)
      ->orderBy('entity_id')
      ->orderBy('revision_id')
      ->orderBy('delta')
      ->execute();

    $dest_records = $lookup = [];
    $current_entity_id = NULL;
    while ($record = $result->fetchAssoc()) {
      $entity_id = $record['entity_id'];
      $value = $record["{$src_field}_target_id"];

      // Reset lookup when we move to a new entity.
      if ($entity_id !== $current_entity_id) {
        $lookup = [];
        $current_entity_id = $entity_id;
      }

      // Did we already create a paragraph for this entity ID + target value?
      // If not, create a new paragraph entity.
      // Otherwise, create a new revision for that paragraph.
      $paragraph = $lookup[$value] ?? NULL;
      if ($paragraph === NULL) {
        $paragraph = $this->createParagraphFromFieldValue($value, 'node', $entity_id, $dest_field);
      }
      elseif ($paragraph instanceof ParagraphInterface) {
        $paragraph->setNewRevision();
        $paragraph->save();
      }

      // Skip record creation if the paragraph couldn't be created.
      if (!$paragraph instanceof ParagraphInterface) {
        continue;
      }

      // Update the lookup table.
      $lookup[$value] = $paragraph;

      // Collect records to be inserted.
      $dest_records[] = [
        'bundle' => $record['bundle'],
        'deleted' => $record['deleted'],
        'entity_id' => $record['entity_id'],
        'revision_id' => $record['revision_id'],
        'langcode' => $record['langcode'],
        'delta' => $record['delta'],
        "{$dest_field}_target_id" => $paragraph->id(),
        "{$dest_field}_target_revision_id" => $paragraph->getRevisionId(),
      ];
    }

    if (!$dest_records) {
      return;
    }

    // Fill destination field revision table records.
    [$dest_data_table, $dest_revision_table] = array_keys($this->getFieldStorageSchema('node', $dest_field));
    $insert_query = $this->database
      ->insert($dest_revision_table)
      ->fields(array_keys($dest_records[0]));
    foreach ($dest_records as $dest_record) {
      $insert_query->values($dest_record);
    }
    $insert_query->execute();

    // Fill destination field data table records.
    // Copy current revision records from revision table to data table.
    $insert_from = $this->database
      ->select($dest_revision_table, 'r')
      ->fields('r')
      ->condition('revision_id', $this->database->select('node', 'n')->fields('n', ['vid']), 'IN')
      ->notExists($this->database->select($dest_data_table, 't')->fields('t'));
    $this->database
      ->insert($dest_data_table)
      ->from($insert_from)
      ->execute();
  }

  /**
   * Returns the storage schema for the given field.
   */
  private function getFieldStorageSchema(string $entity_type, string $field_name): array {
    return $this->keyValue->get('entity.storage_schema.sql')
      ->get("$entity_type.field_schema_data.$field_name", []);
  }

  /**
   * Creates a paragraph from file_url's target_id field value.
   */
  private function createParagraphFromFieldValue(string $value, string $parent_type, int|string $parent_id, string $parent_field_name): ?Paragraph {
    $file_id = $this->extractFileIdFromDereferenceUrl($value);
    $paragraph = $file_id
      ? $this->createResourceFileParagraph($file_id)
      : $this->createResourceLinkParagraph($value);

    if (!$paragraph) {
      return NULL;
    }

    $paragraph->set('parent_type', $parent_type);
    $paragraph->set('parent_id', $parent_id);
    $paragraph->set('parent_field_name', $parent_field_name);
    $paragraph->search_api_skip_tracking = TRUE;
    $paragraph->save();
    return $paragraph;
  }

  /**
   * Extracts file ID from file-dereference URL.
   */
  private function extractFileIdFromDereferenceUrl(string $url): ?int {
    if (preg_match('/\/file-dereference\/(\d+)/', $url, $matches)) {
      return (int) $matches[1];
    }
    return NULL;
  }

  /**
   * Creates a resource_file paragraph.
   */
  private function createResourceFileParagraph(int $file_id): ?Paragraph {
    return Paragraph::create([
      'type' => 'resource_file',
      'resource_file' => $file_id,
    ]);
  }

  /**
   * Creates a resource_link paragraph.
   */
  private function createResourceLinkParagraph(string $url): ?Paragraph {
    $normalized_url = $this->normalizeUrl($url);
    if (!$normalized_url) {
      return NULL;
    }

    return Paragraph::create([
      'type' => 'resource_link',
      'remote_file_url' => $normalized_url,
    ]);
  }

  /**
   * Normalizes and validates the given URL, returns NULL on invalid.
   *
   * @see \Drupal\link\Plugin\Validation\Constraint\LinkTypeConstraintValidator::validate
   */
  private function normalizeUrl(string $url): ?string {
    $url = trim($url);

    // Append missing scheme when missing, if the rest seems valid.
    $parts = parse_url($url);
    if (empty($parts['scheme'])) {
      if (preg_match('/^[a-z0-9.-]+\.[a-z]{2,}(?:\/.*)?$/i', $url)) {
        $url = 'https://' . ltrim($url, '/');
      }
      else {
        return NULL;
      }
    }

    return UrlHelper::isValid($url) && UrlHelper::isExternal($url) ? $url : NULL;
  }

}
