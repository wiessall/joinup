<?php

declare(strict_types=1);

namespace Drupal\joinup_paragraphs\Migrate;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\paragraphs\Entity\Paragraph;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Service for migrating field data between different field types.
 */
final class MigrateData implements ContainerInjectionInterface {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Migrates field data from one field to another.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to migrate fields on.
   * @param string $from_field
   *   The source field name.
   * @param string $to_field
   *   The target field name.
   *
   * @throws \InvalidArgumentException
   *   If the entity doesn't have the required fields.
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   If there are issues saving the entity or paragraphs.
   */
  protected function migrateField(EntityInterface $entity, string $from_field, string $to_field): void {
    if (!$entity->hasField($from_field)) {
      throw new \InvalidArgumentException("Entity does not have field '$from_field'");
    }
    if (!$entity->hasField($to_field)) {
      throw new \InvalidArgumentException("Entity does not have field '$to_field'");
    }

    $source_values = $entity->get($from_field)->getValue();
    if (empty($source_values)) {
      return;
    }

    $target_values = [];
    foreach ($source_values as $source_value) {
      $paragraph_created = $this->createParagraphFromFileValue($source_value);
      if ($paragraph_created) {
        $target_values[] = [
          'target_id' => $paragraph_created->id(),
          'target_revision_id' => $paragraph_created->getRevisionId(),
        ];
      }
    }
    $entity->set($to_field, $target_values);

    $entity->skip_notification = TRUE;
    $entity->setSyncing(TRUE);
    $entity->save();
  }

  /**
   * Creates a paragraph from a file field value.
   *
   * @param array $file_value
   *   The file field value array.
   *
   * @return \Drupal\paragraphs\Entity\Paragraph|null
   *   The created paragraph or NULL if creation failed.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   If there are issues creating or saving the paragraph.
   */
  protected function createParagraphFromFileValue(array $file_value): ?Paragraph {
    if (!isset($file_value['target_id'])) {
      return NULL;
    }

    $target_value = $file_value['target_id'];
    $file_id = $this->extractFileIdFromDereferenceUrl($target_value);

    if ($file_id) {
      return $this->createResourceFileParagraph($file_id);
    }

    return $this->createResourceLinkParagraph($target_value);
  }

  /**
   * Extracts file ID from file-dereference URL.
   *
   * @param string $url
   *   The file-dereference URL.
   *
   * @return int|null
   *   The extracted file ID or NULL if not found.
   */
  protected function extractFileIdFromDereferenceUrl(string $url): ?int {
    if (preg_match('/\/file-dereference\/(\d+)/', $url, $matches)) {
      return (int) $matches[1];
    }
    return NULL;
  }

  /**
   * Creates a resource_file paragraph.
   *
   * @param int $file_id
   *   The file ID.
   *
   * @return \Drupal\paragraphs\Entity\Paragraph|null
   *   The created paragraph or NULL if creation failed.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   If there are issues creating or saving the paragraph.
   */
  protected function createResourceFileParagraph(int $file_id): ?Paragraph {
    $paragraph = Paragraph::create([
      'type' => 'resource_file',
      'resource_file' => $file_id,
    ]);
    $paragraph->save();

    return $paragraph;
  }

  /**
   * Creates a resource_link paragraph.
   *
   * @param string $url
   *   The URL.
   *
   * @return \Drupal\paragraphs\Entity\Paragraph|null
   *   The created paragraph or NULL if creation failed.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   If there are issues creating or saving the paragraph.
   */
  protected function createResourceLinkParagraph(string $url): ?Paragraph {
    if (!$this->isValidUrl($url)) {
      return NULL;
    }

    $paragraph = Paragraph::create([
      'type' => 'resource_link',
      'remote_file_url' => $url,
    ]);
    $paragraph->save();

    return $paragraph;
  }

  /**
   * Migrates fields for a specific node type using batch processing.
   *
   * @param array $sandbox
   *   The batch sandbox array.
   * @param string $node_type
   *   The node type to migrate.
   * @param string $field_from
   *   The source field name.
   * @param string $field_to
   *   The target field name.
   *
   * @return string
   *   Status message for the batch operation.
   */
  public function migrateNodeTypeFields(array &$sandbox, string $node_type, string $field_from, string $field_to): string {
    if (!isset($sandbox['progress'])) {
      $sandbox['progress'] = 0;
      $sandbox['current_index'] = 0;

      $sandbox['node_ids'] = $this->entityTypeManager->getStorage('node')
        ->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', $node_type)
        ->condition($field_from . '.target_id', NULL, 'IS NOT NULL')
        ->sort('nid')
        // TODO: remove
        ->range(0,50)
        ->execute();

      $sandbox['max'] = count($sandbox['node_ids']);
      $sandbox['message'] = "Starting migration of {$sandbox['max']} {$node_type} nodes...";
    }

    $node_ids = array_slice($sandbox['node_ids'], $sandbox['current_index'], 10);
    if (empty($node_ids)) {
      $sandbox['#finished'] = 1;
      return "Migration completed. Processed {$sandbox['progress']} {$node_type} nodes.";
    }

    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($node_ids);

    foreach ($nodes as $node) {
      $sandbox['current_index']++;
      $sandbox['progress']++;

      $this->migrateField($node, $field_from, $field_to);
    }

    $sandbox['#finished'] = $sandbox['progress'] / $sandbox['max'];
    $remaining = $sandbox['max'] - $sandbox['progress'];

    return "Processed {$sandbox['progress']} of {$sandbox['max']} {$node_type} nodes. {$remaining} remaining...";
  }

  /**
   * Validates if a URL has a proper format using Drupal's built-in validation.
   *
   * @param string $url
   *   The URL to validate.
   *
   * @return bool
   *   TRUE if URL is valid, FALSE otherwise.
   */
  protected function isValidUrl(string $url): bool {
    try {
      Url::fromUri($url);
      return TRUE;
    }
    catch (\Exception $e) {
      return FALSE;
    }
  }

}
