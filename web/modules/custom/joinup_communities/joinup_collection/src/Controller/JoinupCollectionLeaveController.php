<?php

declare(strict_types=1);

namespace Drupal\joinup_collection\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\joinup_collection\JoinupCollectionHelper;
use Drupal\joinup_group\Form\LeaveGroupConfirmForm;
use Drupal\node\NodeInterface;

/**
 * Provides an access controller for the 'collection.leave_confirm_form' route.
 */
class JoinupCollectionLeaveController {

  /**
   * Access check for the LeaveCollectionConfirmForm.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The collection that is on the verge of losing a member.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result object.
   */
  public static function access(NodeInterface $node): AccessResultInterface {
    // The 'Joinup' membership can not be revoked.
    if ($node->id() === JoinupCollectionHelper::getCollectionId()) {
      return AccessResult::forbidden();
    }
    return LeaveGroupConfirmForm::access($node);
  }

}
