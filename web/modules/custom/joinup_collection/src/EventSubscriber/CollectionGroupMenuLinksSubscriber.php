<?php

declare(strict_types=1);

namespace Drupal\joinup_collection\EventSubscriber;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\joinup_group\Event\GroupMenuLinksEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Listens to group events.
 */
class CollectionGroupMenuLinksSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      GroupMenuLinksEvent::class => 'addLinks',
    ];
  }

  /**
   * Listens to GroupMenuLinksEvent::ADD_LINKS event.
   *
   * @param \Drupal\joinup_group\Event\GroupMenuLinksEvent $event
   *   The event object.
   */
  public function addLinks(GroupMenuLinksEvent $event): void {
    if ($event->getMenuType() !== 'navigation') {
      return;
    }

    $group = $event->getGroup();
    if ($group instanceof CollectionInterface) {
      $event->addMenuLink($group->toUrl('glossary'), 'Glossary', -7);
    }
  }

}
