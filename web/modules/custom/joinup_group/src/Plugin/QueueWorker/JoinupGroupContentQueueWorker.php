<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Plugin\QueueWorker;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\pathauto\PathautoGeneratorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a worker to consume the 'joinup_group:group_content_update' queue.
 */
#[QueueWorker(
  id: 'joinup_group:group_content_update',
  title: new TranslatableMarkup('Recreate the group content URL aliases'),
  cron: ['time' => 10],
)]
class JoinupGroupContentQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly PathautoGeneratorInterface $aliasGenerator,
    protected readonly QueueFactory $queueFactory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('pathauto.generator'),
      $container->get('queue'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    $entity = $this->entityTypeManager
      ->getStorage($data['entity_type_id'])
      ->load($data['entity_id']);

    if (!$entity) {
      // The entity might have been deleted in the meantime.
      return;
    }

    Cache::invalidateTags($entity->getCacheTagsToInvalidate());

    // Regenerate the group content alias.
    $this->aliasGenerator->updateEntityAlias($entity, 'bulkupdate');

    // Queue alias generation for all other languages.
    $this->queueFactory->get('joinup_i18n:translation_alias')->createItem([
      'entity_type_id' => $data['entity_type_id'],
      'entity_id' => $data['entity_id'],
    ]);
  }

}
