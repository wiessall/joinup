<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\meta_entity\MetaEntityRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Queue worker to disable EIRA terms for pre-wizard solutions.
 *
 * @QueueWorker(
 *   id = "joinup_core_disable_eira_terms",
 *   title = @Translation("Disable EIRA terms for pre-wizard solutions"),
 *   cron = {"time" = 60}
 * )
 */
class DisableEiraTermsSolutionWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a new DisableEiraTermsSolutionWorker object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\meta_entity\MetaEntityRepositoryInterface $metaEntityRepository
   *   The meta entity repository.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected MetaEntityRepositoryInterface $metaEntityRepository,
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
      $container->get('meta_entity.repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    $nid = $data['nid'];
    $node_storage = $this->entityTypeManager->getStorage('node');

    $solution = $node_storage->load($nid);
    if (!$solution) {
      return;
    }

    $meta_entity = $this->metaEntityRepository->getMetaEntityForEntity($solution, 'solution_settings');

    if ($meta_entity && $meta_entity->hasField('list_related_entities')) {
      // Only update if the field is currently set to 1 (enabled).
      if ($meta_entity->get('list_related_entities')->value === '1') {
        $meta_entity->set('list_related_entities', '0');
        $meta_entity->save();
      }
    }
  }

}
