<?php

declare(strict_types=1);

namespace Drupal\eu_oss_vitality_index\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;
use Drupal\eu_oss_vitality_index\VitalityIndexGeneratorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * VitalityIndex queue for the solution.
 *
 * @QueueWorker(
 *   id = "eu_oss_vitality_index",
 *   title = @Translation("Vitality Index worker queue"),
 *   cron = {"time" = 60}
 * )
 */
class VitalityIndexQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $pluginId,
    mixed $pluginDefinition,
    protected VitalityIndexGeneratorInterface $vitalityIndexGenerator,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('eu_oss_vitality_index.generator'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem(mixed $data): void {
    $nodeStorage = $this->entityTypeManager->getStorage('node');
    $solution = $nodeStorage->load($data);

    if ($solution instanceof OssSolutionInterface) {
      $this->vitalityIndexGenerator->processSolution($solution);
    }
  }

}
