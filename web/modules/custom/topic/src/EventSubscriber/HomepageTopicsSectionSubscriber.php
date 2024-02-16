<?php

declare(strict_types=1);

namespace Drupal\topic\EventSubscriber;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent;
use Drupal\layout_builder\LayoutBuilderEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Topic module event subscriber.
 */
class HomepageTopicsSectionSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a new event subscriber instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager) {
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

    /** @var \Drupal\entityqueue\Entity\EntitySubqueue $entity_queue */
    $entity_queue = $this->entityTypeManager->getStorage('entity_subqueue')->load('homepage_topics');
    // If there are no topics, don't show any labels.
    if ($entity_queue->get('items')->isEmpty()) {
      $build['#configuration']['label_display'] = FALSE;
    }
    elseif (!empty($entity_queue->block_label->value)) {
      $build['#configuration']['label'] = $entity_queue->block_label->value;
    }

    CacheableMetadata::createFromObject($entity_queue)
      ->addCacheableDependency($entity_queue->getQueue())
      ->applyTo($build['content']);

    $event->setBuild($build);
  }

}
