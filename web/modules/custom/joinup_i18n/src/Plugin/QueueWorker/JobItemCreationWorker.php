<?php

declare(strict_types=1);

namespace Drupal\joinup_i18n\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Create continuous job items.
 */
#[QueueWorker(
  id: 'joinup_i18n_create_job_items',
  title: new TranslatableMarkup('Create content continuous job items'),
  cron: ['time' => 30],
)]
class JobItemCreationWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $pluginId,
    array $pluginDefinition,
    protected readonly LoggerChannelFactoryInterface $loggerFactory,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    if (!$this->checkData($data)) {
      return;
    }

    $job = $this->entityTypeManager->getStorage('tmgmt_job')->load($data['job']);
    // The job might have been deleted in the meantime.
    if ($job) {
      $job->addItem('content', $data['entity_type'], $data['entity_id'])
        ->addMessage('Continuous job item created');
    }
  }

  /**
   * Checks whether the data is valid and can be processed.
   *
   * @param mixed $data
   *   The queue item data.
   *
   * @return bool
   *   Whether the data is valid and can be processed.
   */
  protected function checkData(mixed $data): bool {
    if (!is_array($data)) {
      $this->loggerFactory->get('joinup_i18n')->error('Invalid item data type: @type', [
        '@type' => gettype($data),
      ]);
      return FALSE;
    }

    if (empty($data['job']) || !is_numeric($data['job'])) {
      $this->loggerFactory->get('joinup_i18n')->error('Invalid job ID');
      return FALSE;
    }

    if (empty($data['entity_type']) || !is_string($data['entity_type'])) {
      $this->loggerFactory->get('joinup_i18n')->error('Invalid entity type: @type', [
        '@type' => var_export($data['entity_type'], TRUE),
      ]);
      return FALSE;
    }

    $entityType = $this->entityTypeManager->getDefinition($data['entity_type'], FALSE);
    if (!$entityType) {
      $this->loggerFactory->get('joinup_i18n')->error("The '@type' entity type doesn't exist.", [
        '@type' => $entityType->id(),
      ]);
      return FALSE;
    }

    // Check that entity still exists.
    $ids = $this->entityTypeManager->getStorage($data['entity_type'])
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition($entityType->getKey('id'), $data['entity_id'])
      ->execute();
    if (!$ids) {
      // The entity has been deleted in the meantime. This is not an error.
      return FALSE;
    }

    return TRUE;
  }

}
