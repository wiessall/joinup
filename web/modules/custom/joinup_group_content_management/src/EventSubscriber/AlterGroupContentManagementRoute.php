<?php

declare(strict_types=1);

namespace Drupal\joinup_group_content_management\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Alter group content management route.
 */
class AlterGroupContentManagementRoute extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('view.group_content_management.manage')) {
      $options = $route->getOptions();
      $options['parameters']['node']['bundle'] = [
        'collection',
        'solution',
      ];
      $route->setOptions($options);
    }
  }

}
