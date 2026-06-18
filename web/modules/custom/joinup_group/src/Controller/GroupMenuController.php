<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Controller;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\og_menu\OgMenuInstanceInterface;

/**
 * Group menu controller.
 */
class GroupMenuController extends ControllerBase {

  /**
   * Provides a custom title callback for the OG Menu instance edit form.
   *
   * @param \Drupal\og_menu\OgMenuInstanceInterface $ogmenu_instance
   *   The OG Menu instance that is being edited.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   The title. as a render array.
   */
  public function __invoke(OgMenuInstanceInterface $ogmenu_instance): MarkupInterface {
    // Provide a custom title for the OG Menu instance edit form. The default
    // menu is suitable for webmasters, but we need a simpler title since this
    // form is exposed to regular visitors.
    $group = $ogmenu_instance->get('og_audience')->entity;
    assert($group instanceof GroupInterface);
    return $this->t('Edit @menu menu of the %group @type', [
      '@menu' => $ogmenu_instance->get('type')->entity->label(),
      '%group' => $ogmenu_instance->label(),
      '@type' => $group->bundle(),
    ]);
  }

}
