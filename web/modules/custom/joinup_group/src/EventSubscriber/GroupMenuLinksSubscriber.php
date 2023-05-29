<?php

declare(strict_types = 1);

namespace Drupal\joinup_group\EventSubscriber;

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
    $group = $event->getGroup();

    $discussionUrl = Url::fromRoute('view.discussions.overview', [
      'rdf_entity' => UriEncoder::encodeUrl($group->id()),
    ]);

    $event
      ->addMenuLink($group->toUrl(), 'Overview', -10)
      ->addMenuLink($group->toUrl('member-overview'), 'Members', -9)
      ->addMenuLink($discussionUrl, 'Discussions', -8)
      ->addMenuLink($group->toUrl('about-page'), 'About', -7);
  }

}
