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
use Drupal\meta_entity\Entity\MetaEntityInterface;
use Drupal\meta_entity\MetaEntityRepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

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
    #[Autowire(service: 'meta_entity.repository')]
    protected readonly MetaEntityRepositoryInterface $metaEntityRepository,
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
   * Adds or removes a topic term from the homepage_topics entityqueue.
   *
   * @param string $uuid
   *   The UUID of the taxonomy_term to add or remove.
   * @param bool $add
   *   TRUE to add the term to the queue, FALSE to remove it.
   */
  public function updateHomepageTopicsQueue(string $uuid, bool $add): void {
    $subqueue = $this->entityTypeManager->getStorage('entity_subqueue')
      ->load('homepage_topics');
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')
      ->loadByProperties(['uuid' => $uuid]);
    $term = reset($terms);
    if (!$subqueue || !$term) {
      return;
    }
    $add ? $subqueue->addItem($term) : $subqueue->removeItem($term);
    $subqueue->save();
  }

  /**
   * Sets the 'Show content' option of a group content listing.
   *
   * @param string $uuid
   *   The UUID of the group node.
   * @param string $option
   *   One of 'all', 'archived' or 'unarchived'.
   */
  public function setGroupListingArchivedOption(string $uuid, string $option): void {
    $nodes = $this->entityTypeManager->getStorage('node')
      ->loadByProperties(['uuid' => $uuid]);
    $node = reset($nodes);
    if (!$node) {
      return;
    }
    $meta_entity = $this->metaEntityRepository
      ->getMetaEntityForEntity($node, $node->bundle() . '_settings');
    if (!$meta_entity instanceof MetaEntityInterface) {
      return;
    }
    $data = $meta_entity->get('listing_content')->getValue();
    if (empty($data)) {
      return;
    }
    $data[0]['value']['show_archived'] = $option;
    $meta_entity->set('listing_content', $data);
    $meta_entity->save();
  }

}
