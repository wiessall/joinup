<?php

declare(strict_types=1);

namespace Drupal\joinup_user\Plugin\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Drupal\user\UserNameItem;

/**
 * Displays the author of the content.
 */
class NodeAuthorRealname extends FieldItemList {

  public function getAuthor(): ?UserInterface {
    if ($this->isEmpty()) {
      return NULL;
    }

    return User::create([
      'name' => $this->get(0)->getValue()['value'],
      // When rendering a user, user.module adds a " (not verified)" suffix to
      // the username if the user is not verified. This is not needed for the
      // cancelled user. Set a flag to consume in the theme.
      'cancelled' => TRUE,
    ]);
  }

}
