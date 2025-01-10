<?php

declare(strict_types=1);

namespace Drupal\joinup_user\Plugin\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * Displays the author of the content.
 */
class NodeAuthorRealname extends FieldItemList {

  /**
   * Returns a new user with the author's name or NULL if the field is empty.
   *
   * @return \Drupal\user\UserInterface|null
   *   The author of the content or NULL if the field is empty.
   */
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
