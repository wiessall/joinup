<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\joinup_core\Archiver;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Queue worker for archiving groups.
 */
#[QueueWorker(
  id: 'joinup_core_archive_groups',
  title: new TranslatableMarkup('Archive groups with fewer than 10 members'),
  cron: ['time' => 60],
)]
final class ArchiveGroups extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected Archiver $archiver,
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
      $container->get(Archiver::class),

    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    $this->archiver->callMethodSafely($data['group_id'], $data['method']);
  }

}
