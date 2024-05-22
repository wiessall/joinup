<?php

declare(strict_types=1);

namespace Drupal\topic\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\topic\PathProcessor\TopicPathProcessor;
use Symfony\Component\Routing\RouteCollection;

/**
 * Alters existing routes related to topic pages.
 */
class TopicRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    if ($route = $collection->get('entity.meta_entity.edit_form')) {
      $route->setOption('_admin_route', FALSE);
    }

    if ($route = $collection->get('entity.taxonomy_term.canonical')) {
      $route->setOption(TopicPathProcessor::REDIRECT_TO_SEARCH_PAGE_OPTION, TRUE);
    }
  }

}
