<?php

declare(strict_types = 1);

namespace Drupal\joinup_oss_catalogue\EventSubscriber;

use Drupal\Core\Render\PageDisplayVariantSelectionEvent;
use Drupal\Core\Render\RenderEvents;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_oss_catalogue\OssCatalogueCollectionInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Selects a page display variant for OSS catalogue landing page.
 */
class JoinupOssCataloguePageDisplayVariant implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      RenderEvents::SELECT_PAGE_DISPLAY_VARIANT => ['landingPage', -101],
    ];
  }

  /**
   * Selects the Joinup Oss catalogue page display variant when case.
   *
   * @param \Drupal\Core\Render\PageDisplayVariantSelectionEvent $event
   *   The event to process.
   */
  public function landingPage(PageDisplayVariantSelectionEvent $event): void {
    $route_match = $event->getRouteMatch();

    if ($route_match->getRouteName() === 'entity.rdf_entity.canonical' &&
      ($group = $route_match->getParameter('rdf_entity')) &&
      $group instanceof GroupInterface &&
      $group->id() === OssCatalogueCollectionInterface::COLLECTION_ENTITY_ID
    ) {
      $event
        ->setPluginId('joinup_oss_catalogue_page')
        ->setPluginConfiguration(['group' => $group]);
    }
  }

}
