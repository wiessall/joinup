<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue\Plugin\QueueWorker;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Downloads the queued remote files.
 *
 * @QueueWorker(
 *   id = "eu_oss_catalogue_imagecache_warmer",
 *   title = @Translation("EU OSS Catalogue remote image cache warmer"),
 *   cron = {"time" = 60},
 * )
 */
class EuOssCatalogueImagecacheWarmerQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a new EuOssCatalogueImageCacheWarmer plugin instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler service.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected ModuleHandlerInterface $moduleHandler,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    if (!$this->moduleHandler->moduleExists('imagecache_external')) {
      // Imagecache External is not installed anymore.
      return;
    }
    imagecache_external_generate_path($data['source']);
  }

}
