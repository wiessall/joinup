<?php

declare(strict_types = 1);

namespace Drupal\joinup_video\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;

/**
 * Provide additional access checks for Media.
 */
class JoinupVideoController extends ControllerBase {

  /**
   * Access check for the group feed view route.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result object.
   */
  public function access(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIf(in_array('moderator', $account->getRoles()));
  }

}
