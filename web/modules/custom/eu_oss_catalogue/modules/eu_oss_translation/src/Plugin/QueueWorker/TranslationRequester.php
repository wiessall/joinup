<?php

declare(strict_types=1);

namespace Drupal\eu_oss_translation\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;
use Drupal\eu_oss_translation\JobBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines 'eu_oss_translation_requester' queue worker.
 *
 * @QueueWorker(
 *   id = "eu_oss_translation_requester",
 *   title = @Translation("Translation requester"),
 *   cron = {"time" = 60},
 * )
 */
class TranslationRequester extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected JobBuilderInterface $jobBuilder,
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
      $container->get('eu_oss_translation.job_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem(mixed $data): void {
    $nodeStorage = $this->entityTypeManager->getStorage('node');
    if ($data['nid'] && ($node = $nodeStorage->load($data['nid']))
      && $node instanceof OssSolutionInterface) {
      $this->jobBuilder->create($node);
    }
  }

}
