<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Entity;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\meta_entity\Entity\MetaEntity;
use Drupal\search_api\Plugin\search_api\datasource\ContentEntityTrackingManager;
use Drupal\search_api\Utility\PostRequestIndexingInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Entity class of the sharing meta entity.
 */
class Sharing extends MetaEntity implements SharingInterface {

  /**
   * Stores the entities which just got connected / disconnected to the sharing.
   *
   * @var \Drupal\Core\Entity\EntityInterface[]
   */
  protected array $changedTargets = [];

  /**
   * {@inheritdoc}
   */
  public function addSharings(array $targets, ?int $expiration): void {
    foreach ($targets as $target) {
      $this->get('field_shared_in')->appendItem([
        'entity' => $target,
        'timestamp' => $expiration,
      ]);
      $this->changedTargets[$target->id()] = $target;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function removeSharings(array $targets): void {
    $targets = array_combine(
      array_map(
        fn (EntityInterface $target): string|int => $target->id(),
        $targets,
      ),
      $targets,
    );

    // Find the delta of the item.
    $fieldItemList = $this->get('field_shared_in');
    $rawValue = $fieldItemList->getValue();
    $keys = array_keys(
      array_filter(
        $rawValue,
        function (array $itemValue) use ($targets): bool {
          return in_array($itemValue['target_id'], array_keys($targets));
        }
      )
    );
    if (empty($keys)) {
      return;
    }

    // ItemList::removeItem() also re-keys the field items, so we should remove
    // the items in reversed delta order.
    foreach (array_reverse($keys) as $key) {
      $fieldItemList->removeItem($key);
      $targetId = $rawValue[$key]['target_id'];
      $this->changedTargets[$targetId] = $targets[$targetId];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    // Tags of the meta entity are invalidated on save, but the share targets
    // and host entities' tags are invalidated here.
    $entities = array_filter(array_merge([$this->getTargetEntity()], $this->changedTargets));
    $tagsToInvalidate = array_reduce(
      $entities,
      fn (array $carry, EntityInterface $entity): array => array_merge($carry, $entity->getCacheTagsToInvalidate()),
      [],
    );
    Cache::invalidateTags($tagsToInvalidate);

    // Reset static storage cache of the host and target groups.
    $idsPerEntityType = array_reduce(
      $entities,
      function (array $carry, EntityInterface $entity): array {
        $carry[$entity->getEntityTypeId()][] = $entity->id();
        return $carry;
      },
      [],
    );
    foreach ($idsPerEntityType as $type => $ids) {
      $storage = \Drupal::entityTypeManager()->getStorage($type);
      assert($storage instanceof EntityStorageInterface);
      $storage->resetCache($ids);
    }

    // Flag entities as needing re-indexing.
    $contentTrackingManager = \Drupal::getContainer()->get('search_api.entity_datasource.tracking_manager', ContainerInterface::NULL_ON_INVALID_REFERENCE);
    if ($contentTrackingManager instanceof ContentEntityTrackingManager) {
      foreach ($entities as $entity) {
        $contentTrackingManager->trackEntityChange($entity);
      }
    }
    parent::preSave($storage);

    $this->changedTargets = [];
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    // Re-index changed entities.
    $postRequestIndexer = \Drupal::getContainer()->get('search_api.post_request_indexing');
    if ($postRequestIndexer instanceof PostRequestIndexingInterface) {
      $postRequestIndexer->destruct();
    }
  }

}
