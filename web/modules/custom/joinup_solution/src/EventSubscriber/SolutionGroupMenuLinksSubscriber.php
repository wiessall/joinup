<?php

declare(strict_types=1);

namespace Drupal\joinup_solution\EventSubscriber;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\joinup_group\Event\GroupMenuLinksEvent;
use Drupal\joinup_solution\Entity\SolutionInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Listens to group events.
 */
class SolutionGroupMenuLinksSubscriber implements EventSubscriberInterface {

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

    $solution = $event->getGroup();
    if ($solution instanceof SolutionInterface) {
      $url = $solution->getCollection()->toUrl('glossary')->mergeOptions([
        'attributes' => [
          'class' => [
            'group-menu-link-external',
          ],
        ],
      ]);
      // Glossary menu link in solution is optional.
      $event->addMenuLink($url, 'Glossary', -7, ['enabled' => FALSE]);
    }
  }

}
