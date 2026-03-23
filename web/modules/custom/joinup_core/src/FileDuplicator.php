<?php

declare(strict_types=1);

namespace Drupal\joinup_core;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\File\FileSystemInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Looks up and duplicates files for specific entities.
 */
class FileDuplicator implements ContainerInjectionInterface {

  /**
   * A list of messages to return after processing, indexed by entity ID.
   *
   * @var array
   */
  protected array $messages = [];

  /**
   * The file target directory.
   *
   * @var string
   */
  protected string $targetDir = 'public://assessments/report/migration';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FileSystemInterface $fileSystem,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('file_system'),
    );
  }

  /**
   * Processes the entities by given criteria.
   */
  public function processEntities(string $entityType, string $bundle, string $fieldName): array {
    $this->fileSystem->prepareDirectory($this->targetDir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
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

    $changed = FALSE;
    $referencedEntities = $fieldItems->referencedEntities();
    if ($fieldItems->count() !== count($referencedEntities)) {
      $changed = TRUE;
      $entity->set($fieldName, $referencedEntities);
      $this->messages[] = sprintf('Removed dead file references for %s entity %s.', $entity->getEntityTypeId(), $entity->id());
    }

    $fileReferences = $entity->get($fieldName)->referencedEntities();
    $fileDirectory = $this->targetDir . '/' . $entity->bundle() . '/' . $entity->id() . '/';
    if (count($fileReferences)) {
      $this->fileSystem->prepareDirectory($fileDirectory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    }

    foreach ($fileReferences as $delta => $file) {
      $originalUri = $file->getFileUri();
      if (!str_contains($originalUri, 'webform/assessment')) {
        continue;
      }

      try {
        $newFile = $file->createDuplicate();
        $newUri = $this->fileSystem->copy($originalUri, $fileDirectory . $newFile->getFilename());
        $newFile->setFileUri($newUri);
        $newFile->save();
        $entity->get($fieldName)->set($delta, $newFile);
        $changed = TRUE;

        $this->messages[] = sprintf('Duplicating URI %s to %s for entity %s.', $originalUri, $newFile->getFileUri(), $entity->id());
      }
      catch (\Exception $e) {
        $this->messages[] = $e->getMessage();
      }
    }

    if ($changed) {
      $entity->save();
    }
  }

}
