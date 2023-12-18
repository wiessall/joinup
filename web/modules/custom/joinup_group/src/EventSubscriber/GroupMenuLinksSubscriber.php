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
    if ($event->getMenuName() === 'navigation') {
      $group = $event->getGroup();
      $discussionUrl = Url::fromRoute('view.discussions.overview', [
        'rdf_entity' => UriEncoder::encodeUrl($group->id()),
      ]);

      $event
        ->addMenuLink($group->toUrl(), 'Overview', -10)
        ->addMenuLink($discussionUrl, 'Discussions', -8);
    }

    if ($event->getMenuName() === 'quick_links') {
      $group = $event->getGroup();

      // @todo Create the default links for collections.
      // - Website link (from the access URL field).
      // - Contact us link.
      // - Glossary link.
    }
  }

}
