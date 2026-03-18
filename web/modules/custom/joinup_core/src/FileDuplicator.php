<?php

declare(strict_types=1);

namespace Drupal\joinup_core;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\File\FileSystemInterface;

/**
 * Looks up and duplicates files for specific entities.
 */
final class FileDuplicator {

  /**
   * List of processed files indexed by their URI.
   *
   * @var array<string, \Drupal\file\FileInterface>
   */
  protected array $processed = [];

  /**
   * A list of messages to return after processing, indexed by entity ID.
   *
   * @var array
   */
  protected array $messages = [];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FileSystemInterface $fileSystem,
  ) {}

  /**
   * Processes the entities by given criteria.
   */
  public function processEntities(string $entityType, string $bundle, string $fieldName): array {
    $entities = $this->loadEntities($entityType, $bundle, $fieldName);
    foreach ($entities as $entity) {
      $this->duplicateFile($entity, $fieldName);
    }

    return $this->messages;
  }

  /**
   * Loads entities that have values in the given file field.
   */
  protected function loadEntities(string $entityTypeId, string $bundle, string $fieldName): array {
    $storage = $this->entityTypeManager->getStorage($entityTypeId);
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', $bundle)
      ->exists($fieldName);

    return $storage->loadMultiple($query->execute());
  }

  /**
   * Checks if a file is already processed and duplicates it if processed.
   */
  protected function duplicateFile(FieldableEntityInterface $entity, string $fieldName): void {
    $fieldItems = $entity->get($fieldName);
    if ($fieldItems->isEmpty()) {
      // Failsafe return.
      return;
    }

    $changed = FALSE;

    $referencedEntities = $fieldItems->referencedEntities();
    $messages = [];
    if ($fieldItems->count() !== count($referencedEntities)) {
      $changed = TRUE;
      $entity->set($fieldName, $referencedEntities);
      $entity->save();
      $messages[] = sprintf('Removed dead file references for %s entity %s.', $entity->getEntityTypeId(), $entity->id());
    }

    foreach ($fieldItems->referencedEntities() as $delta => $file) {
      $originalUri = $file->getFileUri();
      if (!isset($this->processed[$originalUri])) {
        $this->processed[$originalUri] = $file;
        continue;
      }

      try {
        $newFile = $file->createDuplicate();
        $newUri = $this->fileSystem->copy($originalUri, $this::FILE_DIRECTORY . DIRECTORY_SEPARATOR . $newFile->getFilename());
        $newFile->setFileUri($newUri);
        $newFile->save();
        $fieldItems->set($delta, $newFile);
        $this->processed[$newUri] = $file;
        $changed = TRUE;
        $messages[] = sprintf('Duplicated file with URI %s for entity %s.', $originalUri, $entity->id());
      }
      catch (\Exception $e) {
        $messages[] = $e->getMessage();
      }
    }

    $this->messages = array_merge($this->messages, $messages);
    if ($changed) {
      $entity->save();
    }
  }

}
