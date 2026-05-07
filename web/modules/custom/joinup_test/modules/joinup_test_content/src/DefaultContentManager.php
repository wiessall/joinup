<?php

declare(strict_types=1);

namespace Drupal\joinup_test_content;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\InfoParserInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Service to handle default_content.
 */
class DefaultContentManager {

  use MessengerTrait;
  use StringTranslationTrait;

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly ModuleHandlerInterface $moduleHandler,
    protected readonly InfoParserInterface $infoParser,
    protected readonly QueueFactory $queueFactory,
    protected readonly QueueWorkerManagerInterface $queueWorkerManager,
  ) {
  }

  /**
   * Deletes all default content entities imported by the given module.
   */
  public function deleteDefaultContent(string $module_name): void {
    $module_path = $this->moduleHandler->getModule($module_name)->getPathname();
    $info = $this->infoParser->parse($module_path);
    if (empty($info['default_content'])) {
      return;
    }

    foreach ($info['default_content'] as $entity_type => $uuids) {
      $storage = $this->entityTypeManager->getStorage($entity_type);
      foreach ($uuids as $uuid) {
        $entities = $storage->loadByProperties(['uuid' => $uuid]);
        if (!empty($entities)) {
          $storage->delete($entities);
        }
      }
    }

    // Purge orphaned paragraphs.
    $queue = $this->queueFactory->get('entity_reference_revisions_orphan_purger');
    $worker = $this->queueWorkerManager->createInstance('entity_reference_revisions_orphan_purger');
    do {
      $processed = 0;
      while ($item = $queue->claimItem()) {
        $worker->processItem($item->data);
        $queue->deleteItem($item);
        $processed++;
      }
    } while ($processed > 0);

    $this->messenger()->addStatus($this->t('Deleted all default content entities from @module.', ['@module' => $module_name]));
  }

  /**
   * Sets meta_entity.type.topic mapping.taxonomy_term.topic.auto_create.
   */
  public function setTopicAutoCreate(bool $auto_create): void {
    $type = $this->entityTypeManager
      ->getStorage('meta_entity_type')
      ->load('topic');
    if (!$type) {
      return;
    }
    $mapping = $type->get('mapping');
    $mapping['taxonomy_term']['topic']['auto_create'] = $auto_create;
    $type->set('mapping', $mapping);
    $type->save();
  }

}
