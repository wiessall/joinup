<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Plugin\QueueWorker;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\joinup_group\Entity\ShareableContentInterface;
use Drupal\meta_entity\Entity\MetaEntityInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Worker to removes expired sharings.
 */
#[QueueWorker(
  id: 'joinup_group_expired_sharing',
  title: new TranslatableMarkup('Removes expired sharings'),
  cron: ['time' => 15],
)]
class ExpiredSharingQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly TimeInterface $time,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('datetime.time'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    $sharingMeta = $this->entityTypeManager->getStorage('meta_entity')->load($data);
    if (
      !$sharingMeta instanceof MetaEntityInterface ||
      !$sharingMeta->hasField('field_shared_in')
    ) {
      return;
    }

    // Using request time to stabilize the timestamp during the same queue
    // process.
    $timeStamp = $this->time->getRequestTime();
    $expiredSharingIds = array_reduce(
      $sharingMeta->get('field_shared_in')->getValue(),
      function (array $carry, array $properties) use ($timeStamp): array {
        if ($properties['timestamp'] <= $timeStamp) {
          $carry[] = $properties['target_id'];
        }
        return $carry;
      },
      [],
    );

    $sharedContent = $sharingMeta->getTargetEntity();
    if (!$sharedContent instanceof ShareableContentInterface) {
      $sharingMeta->delete();
    }

    $sharedContent->unShareFrom(
      $this->entityTypeManager->getStorage('node')->loadMultiple($expiredSharingIds)
    );
  }

}
