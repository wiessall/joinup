<?php

declare(strict_types = 1);

namespace Drupal\eif\EventSubscriber;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\joinup_group\Event\GroupMenuLinksEvent;
use Drupal\solution\Entity\SolutionInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Listens to group events.
 */
class EifGroupMenuLinksSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      GroupMenuLinksEvent::ADD_EIF_PERSPECTIVE_LINK => 'addEifPerspectiveLink'
    ];
  }

  /**
   * Listens to GroupMenuLinksEvent::ADD_EIF_PERSPECTIVE_LINK event.
   *
   * @param \Drupal\joinup_group\Event\GroupMenuLinksEvent $event
   *   The event object.
   */
  public function addEifPerspectiveLink(GroupMenuLinksEvent $event): void {
    $group = $event->getGroup();

    if ($group instanceof SolutionInterface) {
      $link = ['uri' => $group->toUrl('eif-perspective')->toUriString()];
      $event->addMenuLink($link, $this->t('EIF view'), -6);
    }
  }

}
