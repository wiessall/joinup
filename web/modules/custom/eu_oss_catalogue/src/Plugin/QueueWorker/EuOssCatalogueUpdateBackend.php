<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\eu_oss_catalogue\EuOssCatalogueInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Consumes the 'eu_oss_catalogue' queue.
 *
 * @QueueWorker(
 *   id = "eu_oss_catalogue",
 *   title = @Translation("EU OSS Catalogue queue worker"),
 *   cron = {"time" = 10},
 * )
 */
class EuOssCatalogueUpdateBackend extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a new worker instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $pluginId
   *   The plugin_id for the plugin instance.
   * @param mixed $pluginDefinition
   *   The plugin implementation definition.
   * @param \Drupal\eu_oss_catalogue\EuOssCatalogueInterface $catalogueService
   *   The EU OSS catalogue service.
   */
  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    protected EuOssCatalogueInterface $catalogueService,
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $pluginId, $pluginDefinition): self {
    return new static(
      $configuration,
      $pluginId,
      $pluginDefinition,
      $container->get('eu_oss_catalogue'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    $operation = $data['operation'] ?? NULL;
    if ($operation === 'solution:create') {
      $this->catalogueService->createOrUpdateSolution($data['provider'], $data['solution']);
    }
    elseif ($operation === 'solution:create:cleanup') {
      $this->catalogueService->prepareCleanup($data['provider'], $data['solution_urls']);
    }
    elseif ($operation === 'solution:provider_remove') {
      $this->catalogueService->cleanupOrUpdate($data['provider'], $data['solution_ids']);
    }
    else {
      throw new \InvalidArgumentException('Operation not set or invalid');
    }
  }

}
