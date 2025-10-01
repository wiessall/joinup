<?php

declare(strict_types=1);

namespace Drupal\joinup_group\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\joinup_group\Event\GroupMenuLinksEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Listens to group events.
 */
class GroupMenuLinksSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

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
    $ogMenuInstanceId = substr($event->getMenuName(), 7);
    $menu = $this->entityTypeManager->getStorage('ogmenu_instance')->load($ogMenuInstanceId);
    $group = $event->getGroup();
    $parameters = [$group->getEntityTypeId() => $group->id()];
    if ($menu->bundle() === 'navigation') {
      $discussionUrl = Url::fromRoute('view.discussions.overview', $parameters);

      $event
        ->addMenuLink($group->toUrl(), 'Overview', -10)
        ->addMenuLink($discussionUrl, 'Discussions', -8);
    }

    if ($menu->bundle() === 'quick_links') {
      $event->addMenuLink(Url::fromRoute('joinup_menu.website_link', $parameters), 'Website link');

      if ($group->bundle() === 'solution') {
        $event->addMenuLink(Url::fromRoute('joinup_release.solution_release.overview', $parameters), 'Release history');
      }
    }
  }

}
