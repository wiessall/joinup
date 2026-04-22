<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\OrderAfter;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\file\Entity\File;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\node\NodeInterface;
use Drupal\og\MembershipManagerInterface;
use Drupal\search_api\Plugin\search_api\datasource\ContentEntityTrackingManager;

/**
 * Hook implementations for group node presave and update operations.
 */
class GroupNodeHooks {

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly FileSystemInterface $fileSystem,
    protected readonly ExtensionPathResolver $extensionPathResolver,
    protected readonly MembershipManagerInterface $membershipManager,
    protected readonly ?ContentEntityTrackingManager $contentTrackingManager,
    protected readonly LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /**
   * Implements hook_ENTITY_TYPE_presave().
   *
   * Provide default images for collections and solutions in case they are
   * empty.
   *
   * Also defers Entity Browser immediate indexing during group updates to avoid
   * recursive rendering (group node -> listing_content -> meta_entity -> group
   * node).
   *
   * @see ::entityUpdateStopBatchTracking()
   */
  #[Hook('node_presave')]
  public function nodePresave(NodeInterface $entity): void {
    if (!$entity instanceof GroupInterface) {
      return;
    }

    if (!$entity->isNew()) {
      /** @var \Drupal\search_api\IndexInterface $index */
      $index = $this->entityTypeManager->getStorage('search_api_index')->load('entity_browser');
      $index?->startBatchTracking();
    }

    $fixturesPath = $this->extensionPathResolver->getPath('module', 'joinup_group') . '/fixtures/images/';
    if ($entity->hasField('logo') && empty($entity->get('logo')->getValue())) {
      // Get files without the . and .. directories.
      $files = array_slice(scandir($fixturesPath . 'logo'), 2);
      $random = array_rand($files);
      $filePath = $this->fileSystem->realpath($fixturesPath . 'logo/' . $files[$random]);
      $filePath = $this->fileSystem->copy($filePath, 'public://');
      $file = File::create(['uri' => $filePath]);
      $file->save();
      $entity->set('logo', [
        'target_id' => $file->id(),
        'alt' => $entity->label(),
      ]);
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_update().
   *
   * Re-index all group content when a group is saved with a changed status.
   *
   * The hasPublished flag ensures that this will occur only when an entity is
   * published for the first time.
   */
  #[Hook('node_update')]
  public function nodeUpdate(NodeInterface $entity): void {
    if (!$entity instanceof GroupInterface) {
      return;
    }

    if (isset($entity->search_api_skip_tracking) && $entity->search_api_skip_tracking) {
      // Respect the flag that set the indexing to be skipped during update.
      // This can be useful e.g. during deployments where the entities are
      // going to be re-indexed anyway, but these updates would delay
      // the deployment by a lot.
      return;
    }

    if ($entity->isPublished() && $this->contentTrackingManager) {
      $contentIds = $this->membershipManager->getGroupContentIds($entity, ['node']);
      $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($contentIds['node']);
      foreach ($nodes as $node) {
        $this->contentTrackingManager->trackEntityChange($node);
      }
    }

    // Log an informational message if contact was changed.
    $contactInfoFieldName = $entity->getContactInformationFieldName();
    $contactInfoField = $entity->get($contactInfoFieldName);
    $original = $entity->getOriginal();
    if (
      $original &&
      !$contactInfoField->equals($originalField = $original->get($contactInfoFieldName))
    ) {
      $this->loggerFactory->get('joinup_group')->info(
        "Contact info was changed on group '%group-label'. Previous contact ID was '%previous-id', new one's ID is '%new-id'.",
        [
          '%group-label' => $entity->label(),
          '%previous-id' => $originalField->entity?->id(),
          '%new-id' => $contactInfoField->entity?->id(),
        ]
      );
    }
  }

  /**
   * Implements hook_entity_update().
   *
   * Stops batch tracking on the Entity Browser index started in
   * nodePresave(). Ordered after search_api so that search_api_entity_update()
   * runs while batch tracking is still active.
   *
   * @see ::nodePresave()
   */
  #[Hook('entity_update', order: new OrderAfter(modules: ['search_api']))]
  public function entityUpdateStopBatchTracking(EntityInterface $entity): void {
    if (!$entity instanceof GroupInterface) {
      return;
    }

    /** @var \Drupal\search_api\IndexInterface $index */
    $index = $this->entityTypeManager->getStorage('search_api_index')->load('entity_browser');
    if ($index?->isBatchTracking()) {
      $index->stopBatchTracking();
    }
  }

}
