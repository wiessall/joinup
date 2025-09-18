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
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\track_usage\RecorderInterface;
use Drush\Drush;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Service for migrating field data between different field types.
 */
final class MigrateFileUrl implements ContainerInjectionInterface {

  use AutowireTrait;
  use StringTranslationTrait;

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire('config.storage.sync')]
    protected readonly StorageInterface $syncStorage,
    protected readonly Connection $database,
    #[Autowire('keyvalue')]
    protected readonly KeyValueFactory $keyValue,
    #[Autowire('Drupal\track_usage\Recorder')]
    protected readonly RecorderInterface $recorder,
    protected readonly QueueFactory $queue,
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
   * Re-compute track_usages data for affected sources.
   */
  public function deploy(): void {
    $nids = $this->entityTypeManager->getStorage('node')->getQuery()->accessCheck(TRUE)->exists('resources')->execute();
    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);

    foreach ($nodes as $node) {
      $this->queue->get('track_usage')->createItem(
        [
          'type' => $node->getType(),
          'ids' => $node->id(),
          'revision' => $node->getRevisionId(),
          'config' => 'file_visibility',
        ]
      );
    }
  }

  /**
   * Prepare paragraph's structure.
   */
  private function prepareParagraphs(): void {
    $configEntities = [
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

    $this->createConfigEntities($configEntities);
  }

  /**
   * Prepare node's structure.
   */
  private function prepareNodeFields(): void {
    $configEntities = [
      'field_storage_config' => [
        'field.storage.node.resources',
      ],
      'field_config' => [
        'field.field.node.document.resources',
        'field.field.node.distribution.resources',
        'field.field.node.solution.resources',
      ],
    ];

    $this->createConfigEntities($configEntities);
  }

  /**
   * Creates configuration entities from sync storage.
   *
   * @param array $configEntities
   *   Array of configuration entities keyed by storage type.
   */
  private function createConfigEntities(array $configEntities): void {
    foreach ($configEntities as $storage => $keys) {
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
  private function migrateNodeFileUrlField(string $srcField, string $destField): void {
    $startTime = microtime(TRUE);
    // Process all revision table records.
    [, $srcRevisionTable] = array_keys($this->getFieldStorageSchema('node', $srcField));
    $results = $this->database->select($srcRevisionTable)
      ->fields($srcRevisionTable)
      ->orderBy('entity_id')
      ->orderBy('revision_id')
      ->orderBy('delta')
      ->execute();
    $this->deleteTrackUsages($srcField);
    $destRecords = $lookup = [];
    $currentEntityId = NULL;
    while ($record = $results->fetch()) {
      $entityId = $record->entity_id;
      $value = $record->{"{$srcField}_target_id"};

      // Reset lookup when we move to a new entity.
      if ($entityId !== $currentEntityId) {
        $lookup = [];
        $currentEntityId = $entityId;
      }

      // Did we already create a paragraph for this entity ID + target value?
      // If not, create a new paragraph entity.
      // Otherwise, create a new revision for that paragraph.
      $paragraph = $lookup[$value] ?? NULL;
      if ($paragraph === NULL) {
        $paragraph = $this->createParagraphFromFieldValue($value, 'node', $entityId, $destField);
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
      $destRecords[] = [
        'bundle' => $record->bundle,
        'deleted' => $record->deleted,
        'entity_id' => $record->entity_id,
        'revision_id' => $record->revision_id,
        'langcode' => $record->langcode,
        'delta' => $record->delta,
        "{$destField}_target_id" => $paragraph->id(),
        "{$destField}_target_revision_id" => $paragraph->getRevisionId(),
      ];
    }

    if (!$destRecords) {
      return;
    }

    // Fill destination field revision table records.
    [$destDataTable, $destRevisionTable] = array_keys($this->getFieldStorageSchema('node', $destField));
    $insertQuery = $this->database
      ->insert($destRevisionTable)
      ->fields(array_keys($destRecords[0]));
    foreach ($destRecords as $destRecord) {
      $insertQuery->values($destRecord);
    }
    $insertQuery->execute();

    // Fill destination field data table records.
    // Copy current revision records from revision table to data table.
    $insertFrom = $this->database
      ->select($destRevisionTable, 'r')
      ->fields('r')
      ->condition('revision_id', $this->database->select('node', 'n')->fields('n', ['vid']), 'IN')
      ->notExists($this->database->select($destDataTable, 't')->fields('t')->where('t.revision_id = r.revision_id'));
    $this->database
      ->insert($destDataTable)
      ->from($insertFrom)
      ->execute();

    // Select the distinct entity IDs that were processed both from the source
    // and destination data tables.
    $sourceDataTable = $this->getFieldStorageSchema('node', $srcField);
    [$sourceDataTable] = array_keys($sourceDataTable);
    $sourceEntityIds = $this->database->select($sourceDataTable, 's')
      ->fields('s', ['entity_id', 'revision_id', 'delta'])
      ->execute()
      ->fetchCol();
    sort($sourceEntityIds);

    // Select the distinct entity IDs that are in the destination data table.
    $destEntityIds = $this->database->select($destDataTable, 'd')
      ->fields('d', ['entity_id', 'revision_id', 'delta'])
      // Only care about entity IDs that are in the source table. That is
      // because there are entity IDs from other sequences.
      ->condition('d.entity_id', $sourceEntityIds, 'IN')
      ->execute()
      ->fetchCol();
    sort($destEntityIds);

    $endTime = microtime(TRUE);
    Drush::logger()->notice($this->t('Migrated records from @src to @dest field in @time seconds.', [
      '@src' => $srcField,
      '@dest' => $destField,
      '@time' => round($endTime - $startTime, 2),
    ]));

    if ($sourceEntityIds !== $destEntityIds) {
      $missingInDest = array_diff($sourceEntityIds, $destEntityIds);
      Drush::logger()->warning($this->t('@countSource entries on the source table, but @countTarget to the target table. The following entity IDs were missing in the destination field data table: @ids', [
        '@countSource' => count($sourceEntityIds),
        '@countTarget' => count($destEntityIds),
        '@ids' => implode(', ', $missingInDest),
      ]));
    }
    else {
      Drush::logger()->notice($this->t('All entity IDs were successfully migrated to the destination field data table.'));
    }
  }

  /**
   * Returns the storage schema for the given field.
   */
  private function getFieldStorageSchema(string $entityType, string $fieldName): array {
    return $this->keyValue->get('entity.storage_schema.sql')
      ->get("$entityType.field_schema_data.$fieldName", []);
  }

  /**
   * Creates a paragraph from file_url's target_id field value.
   */
  private function createParagraphFromFieldValue(string $value, string $parentType, int|string $parentId, string $parentFieldName): ?Paragraph {
    $fileId = $this->extractFileIdFromDereferenceUrl($value);
    $paragraph = $fileId
      ? $this->createResourceFileParagraph($fileId)
      : $this->createResourceLinkParagraph($value);

    if (!$paragraph) {
      return NULL;
    }

    $paragraph->set('parent_type', $parentType);
    $paragraph->set('parent_id', $parentId);
    $paragraph->set('parent_field_name', $parentFieldName);
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
  private function createResourceFileParagraph(int $fileId): ?Paragraph {
    return Paragraph::create([
      'type' => 'resource_file',
      'resource_file' => $fileId,
    ]);
  }

  /**
   * Creates a resource_link paragraph.
   */
  private function createResourceLinkParagraph(string $url): ?Paragraph {
    $normalizedUrl = $this->normalizeUrl($url);
    if (!$normalizedUrl) {
      return NULL;
    }

    return Paragraph::create([
      'type' => 'resource_link',
      'remote_file_url' => $normalizedUrl,
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

  /**
   * Deletes track usages by srcField.
   *
   * @param string $srcField
   *   The source field.
   */
  private function deleteTrackUsages(string $srcField): void {
    $query = $this->database->select('track_usage', 'tu');
    $query->addField('tu', 'tid');
    $query->innerJoin('node__' . $srcField, 'src', 'src.entity_id = tu.source_id_int');
    $query->innerJoin('track_usage_paths', 'tup', 'tup.tid = tu.tid');
    $query->condition('src.' . $srcField . '_target_id', '%/file-dereference/%', 'LIKE');
    $query->where("tu.target_id_int = CAST(SUBSTRING_INDEX(src." . $srcField . "_target_id, '/', -1) AS UNSIGNED)");

    $tids = $query->execute()->fetchCol();

    if (!empty($tids)) {
      $this->database->delete('track_usage_paths')
        ->condition('tid', $tids, 'IN')
        ->execute();

      $this->database->delete('track_usage')
        ->condition('tid', $tids, 'IN')
        ->execute();
    }
  }

  /**
   * Get the right file object from an URL.
   *
   * @param string $url
   *   URL.
   *
   * @return \Drupal\file\FileInterface|null
   *   The file object.
   *
   * @see \Drupal\file_url\FileUrlHandler::urlToFile
   */
  private static function urlToFile($url): ?FileInterface {
    // Not a url, but a normal file ID. This can occur when an object is
    // created in code (not through a form).
    if (is_numeric($url)) {
      return File::load($url);
    }
    $matches = [];
    preg_match('/\/file-dereference\/([0-9]+)/', $url, $matches);
    if (!empty($matches)) {
      return File::load($matches[1]);
    }

    // It's a remote file.
    return NULL;
  }

}
