<?php

declare(strict_types=1);

namespace Drupal\joinup_group\EventSubscriber;

use Drupal\Core\Render\PageDisplayVariantSelectionEvent;
use Drupal\Core\Render\RenderEvents;
use Drupal\joinup_group\Entity\GroupInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Selects a page display variant to allow group landing page customization.
 */
class JoinupGroupPageDisplayVariantSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      // Act after all Drupal core subscribers.
      RenderEvents::SELECT_PAGE_DISPLAY_VARIANT => ['groupLandingPage', -100],
    ];
  }

  /**
   * Selects the Joinup group page display variant when case.
   *
   * @param \Drupal\Core\Render\PageDisplayVariantSelectionEvent $event
   *   The event to process.
   */
  public function groupLandingPage(PageDisplayVariantSelectionEvent $event): void {
    $route_match = $event->getRouteMatch();
    if ($route_match->getRouteName() === 'entity.node.canonical' && ($group = $route_match->getParameter('node')) && $group instanceof GroupInterface) {
      $event
        ->setPluginId('joinup_group_canonical_page')
        ->setPluginConfiguration(['group' => $group]);
    }
  }

}
