<?php

declare(strict_types=1);

namespace Drupal\joinup_paragraphs;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Service for migrating field data between different field types.
 */
readonly class FieldMigration implements FieldMigrationInterface {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function migrateField(EntityInterface $entity, string $from_field, string $to_field): void {
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
    // Create the resource_file paragraph.
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
    // Skip if URL is not valid using Drupal's built-in validation.
    if (!$this->isValidUrl($url)) {
      return NULL;
    }

    // Create the resource_link paragraph.
    $paragraph = Paragraph::create([
      'type' => 'resource_link',
      'remote_file_url' => $url,
    ]);
    $paragraph->save();

    return $paragraph;
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
