<?php

declare(strict_types=1);

namespace Drupal\joinup_group\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\joinup_group\Event\GroupMenuLinksEvent;
use Drupal\sparql_entity_storage\UriEncoder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Listens to group events.
 */
class GroupMenuLinksSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * Constructs a new GroupMenuLinkSubscriber instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager) {}

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
    if ($menu->bundle() === 'navigation') {
      $discussionUrl = Url::fromRoute('view.discussions.overview', [
        'rdf_entity' => UriEncoder::encodeUrl($group->id()),
      ]);

      $event
        ->addMenuLink($group->toUrl(), 'Overview', -10)
        ->addMenuLink($discussionUrl, 'Discussions', -8);
    }

    if ($menu->bundle() === 'quick_links') {
      $group = $event->getGroup();
      $parameters = ['rdf_entity' => UriEncoder::encodeUrl($group->id())];
      $event->addMenuLink(Url::fromRoute('joinup_menu.website_link', $parameters), 'Website link');

      if ($group->bundle() === 'solution') {
        $event->addMenuLink(Url::fromRoute('asset_release.solution_asset_release.overview', $parameters), 'Release history');
        $event->addMenuLink(Url::fromRoute('joinup_menu.documentation_file', $parameters), 'Documentation');
      }
    }
  }

}
