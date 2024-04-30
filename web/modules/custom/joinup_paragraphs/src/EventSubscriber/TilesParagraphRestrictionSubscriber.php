<?php

declare(strict_types=1);

namespace Drupal\joinup_paragraphs\EventSubscriber;

use Drupal\layout_paragraphs\Event\LayoutParagraphsAllowedTypesEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Tiles paragraph restriction subscriber.
 */
class TilesParagraphRestrictionSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      LayoutParagraphsAllowedTypesEvent::EVENT_NAME => 'tilesRestriction',
    ];
  }

  /**
   * Restricts usage of tiles by layout paragraph.
   *
   * @param \Drupal\layout_paragraphs\Event\LayoutParagraphsAllowedTypesEvent $event
   *   The allowed type event.
   */
  public function tilesRestriction(LayoutParagraphsAllowedTypesEvent $event): void {
    $types = $event->getTypes();
    if (!$this->isLayout($event, 'layout_onecol') && isset($types['tiles'])) {
      unset($types['tiles']);

      $event->setTypes($types);
    }
  }

  /**
   * Checks if we have a selected layout.
   *
   * @param \Drupal\layout_paragraphs\Event\LayoutParagraphsAllowedTypesEvent $event
   *   The allowed type event.
   * @param string $layoutName
   *   Layout name.
   *
   * @return bool
   *   True if we have a selected layout.
   */
  protected function isLayout(LayoutParagraphsAllowedTypesEvent $event, string $layoutName): bool {
    $parent = $event->getLayout()->getComponentByUuid($event->getParentUuid());
    if (!$parent) {
      return FALSE;
    }
    $parentSettings = $parent->getSettings();

    return $parentSettings['layout'] === $layoutName;
  }

}
