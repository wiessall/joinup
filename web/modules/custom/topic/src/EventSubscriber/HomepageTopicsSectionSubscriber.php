<?php

declare(strict_types = 1);

namespace Drupal\topic\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent;
use Drupal\layout_builder\LayoutBuilderEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Topic module event subscriber.
 */
class HomepageTopicsSectionSubscriber implements EventSubscriberInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a HomepageTopicsSectionSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      LayoutBuilderEvents::SECTION_COMPONENT_BUILD_RENDER_ARRAY => [
        'onBuildRender',
        100,
      ],
    ];
  }

  /**
   * Alters the title of the main topics block.
   *
   * @param \Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent $event
   *   The fired event.
   */
  public function onBuildRender(SectionComponentBuildRenderArrayEvent $event): void {
    $build = $event->getBuild();
    if (empty($build['#plugin_id']) || $build['#plugin_id'] !== 'views_block:homepage_topics-block_1') {
      return;
    }
    $entity_queue = $this->entityTypeManager->getStorage('entity_subqueue')->load('homepage_topics');
    if (!empty($entity_queue->block_label->value)) {
      $build['#configuration']['label'] = $entity_queue->block_label->value;
    }
    $event->setBuild($build);
  }

}
