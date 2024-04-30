<?php

declare(strict_types=1);

namespace Drupal\joinup_group_content_management\Plugin\views\field;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\views\Plugin\views\field\EntityLink;
use Drupal\views\ResultRow;

/**
 * Present a link to an entity even the entity cannot be accessed.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("accessible_entity_link")
 */
class AccessibleEntityLink extends EntityLink {

  /**
   * {@inheritdoc}
   */
  public function checkUrlAccess(ResultRow $row): AccessResultInterface {
    return AccessResult::allowed();
  }

}
