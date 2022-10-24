<?php

declare(strict_types = 1);

namespace Drupal\joinup_licence\Plugin\Menu;

use Drupal\Core\Menu\MenuLinkDefault;

/**
 * Represents a menu link for legal type overview page.
 */
class LegalTypeOverviewMenuLink extends MenuLinkDefault {

  /**
   * {@inheritdoc}
   */
  public function getRouteParameters() {
    return [
      'taxonomy_vocabulary' => 'legal_type',
    ];
  }

}
