<?php

declare(strict_types=1);

namespace Drupal\joinup_core;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\File\FileSystemInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

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
    #[Autowire('logger.channel.joinup_core')]
    private readonly LoggerInterface $loggerChannelJoinupCore,
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
    if ($fieldItems->count() !== count($referencedEntities)) {
      $changed = TRUE;
      $entity->set($fieldName, $referencedEntities);
      $entity->save();

      $this->loggerChannelJoinupCore->warning('Removed dead file references for @entityTypeId entity @entity.', [
        '@entityTypeId' => $entity->getEntityTypeId(),
        '@entity' => $entity->id(),
      ]);
      $this->messages[$entity->id()] = sprintf('Removed dead file references for %s entity %s.', $entity->getEntityTypeId(), $entity->id());
    }

    foreach ($fieldItems->referencedEntities() as $delta => $file) {
      $originalUri = $file->getFileUri();

      // Skip already processed files.
      if (!isset($this->processed[$originalUri])) {
        $this->processed[$originalUri] = $file;
        continue;
      }

      try {
        $newFile = $file->createDuplicate();
        $newUri = $this->fileSystem->copy($originalUri, $file->getFileUri());
        $newFile->setFileUri($newUri);
        $newFile->save();
        $fieldItems->set($delta, $newFile);
        $this->processed[$newUri] = $file;
        $changed = TRUE;

        $this->loggerChannelJoinupCore->info('Duplicated file with URI @uri for entity @entity.', [
          '@uri' => $originalUri,
          '@entity' => $entity->id(),
        ]);

        $this->messages[$entity->id()] = sprintf('Duplicated file with URI %s for entity %s.', $originalUri, $entity->id());
      }
      catch (\Exception $e) {
        $this->loggerChannelJoinupCore->error('Failed to duplicate file with URI @uri for entity @entity: @message', [
          '@uri' => $originalUri,
          '@entity' => $entity->id(),
          '@message' => $e->getMessage(),
        ]);
        $this->messages[$entity->id()] = $e->getMessage();
      }
    }

    if ($changed) {
      $entity->save();
    }
  }

}
