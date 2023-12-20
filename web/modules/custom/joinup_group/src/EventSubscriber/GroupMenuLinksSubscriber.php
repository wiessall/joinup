<?php

declare(strict_types = 1);

namespace Drupal\joinup_group\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\joinup_group\Event\GroupMenuLinksEvent;
use Drupal\joinup_menu\MenuHelperInterface;
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
   * @param \Drupal\joinup_menu\MenuHelperInterface $menuHelper
   *   The menu helper service.
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager, protected MenuHelperInterface $menuHelper) {}

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
    if ($menu->bundle() === 'navigation') {
      $group = $event->getGroup();
      $discussionUrl = Url::fromRoute('view.discussions.overview', [
        'rdf_entity' => UriEncoder::encodeUrl($group->id()),
      ]);

      $event
        ->addMenuLink($group->toUrl(), 'Overview', -10, [
          'internal_identifier' => MenuHelperInterface::INTERNAL_IDENTIFIER_OVERVIEW,
        ])
        ->addMenuLink($discussionUrl, 'Discussions', -8, [
          'internal_identifier' => MenuHelperInterface::INTERNAL_IDENTIFIER_DISCUSSIONS,
        ]);
    }

    if ($menu->bundle() === 'quick_links') {
      $group = $event->getGroup();
      if ($group->bundle() === 'collection') {
        $event->addMenuLink($this->menuHelper->getQuickLinksUrlForField($group, 'field_ar_access_url'), 'Website link', -7, [
          'enabled' => $group->get('field_ar_access_url')->isEmpty() === FALSE,
          'internal_identifier' => MenuHelperInterface::INTERNAL_IDENTIFIER_WEBSITE_LINK,
        ]);
        $event->addMenuLink($this->menuHelper->getQuickLinksUrlForField($group, 'field_ar_contact_information'), 'Contact us', -6, [
          'enabled' => $group->get('field_ar_contact_information')->isEmpty() === FALSE,
          'internal_identifier' => MenuHelperInterface::INTERNAL_IDENTIFIER_CONTACT_US,
        ]);
      }
      elseif ($group->bundle() === 'solution') {
        $event->addMenuLink($this->menuHelper->getQuickLinksUrlForField($group, 'field_is_landing_page'), 'Website link', -7, [
          'internal_identifier' => MenuHelperInterface::INTERNAL_IDENTIFIER_WEBSITE_LINK,
        ]);
        $event->addMenuLink(Url::fromRoute('asset_release.solution_asset_release.overview', [
          'rdf_entity' => $group->id(),
        ]), 'Release history', -6, [
          'internal_identifier' => MenuHelperInterface::INTERNAL_IDENTIFIER_RELEASES,
        ]);
        // Leave -5 for the EIF perspective link.
        // @see \_eif_add_eif_link_to_leftside_menu
        $event->addMenuLink($this->menuHelper->getQuickLinksUrlForField($group, 'field_is_documentation'), 'Documentation', -4, [
          'internal_identifier' => MenuHelperInterface::INTERNAL_IDENTIFIER_DOCUMENTATION,
        ]);
        $event->addMenuLink($this->menuHelper->getQuickLinksUrlForField($group, 'field_is_contact_information'), 'Contact us', -3, [
          'internal_identifier' => MenuHelperInterface::INTERNAL_IDENTIFIER_CONTACT_US,
        ]);
      }
    }
  }

}
