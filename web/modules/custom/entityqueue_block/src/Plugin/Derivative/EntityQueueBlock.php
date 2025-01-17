<?php

declare(strict_types=1);

namespace Drupal\entityqueue_block\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides block plugin definitions for entityqueues.
 *
 * @see \Drupal\entityqueue_block\Plugin\Block\EntityQueueBlock
 */
class EntityQueueBlock extends DeriverBase implements ContainerDeriverInterface {

  public function __construct(protected readonly EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    /** @var \Drupal\entityqueue\EntityQueueInterface[] $queues */
    $queues = $this->entityTypeManager->getStorage('entity_queue')->loadMultiple();
    foreach ($queues as $id => $queue) {
      $this->derivatives[$id] = $base_plugin_definition;
      $this->derivatives[$id]['admin_label'] = $queue->label();
      $this->derivatives[$id]['config_dependencies']['config'] = [$queue->getConfigDependencyName()];
    }
    return $this->derivatives;
  }

}
