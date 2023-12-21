<?php

declare(strict_types = 1);

namespace Drupal\solution\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\solution\Entity\SolutionInterface;

/**
 * Action to archive solutions.
 *
 * @Action(
 *    id = "archive_solution",
 *    label = @Translation("Archive solution"),
 *    type = "rdf_entity",
 *  )
 */
class ArchiveSolutionAction extends ActionBase {

  /**
   * ID of the archived state.
   */
  protected const ARCHIVED_STATE_ID = 'archived';

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $access = AccessResult::forbidden();
    if (
      $object instanceof SolutionInterface &&
      $object->getWorkflowState() !== self::ARCHIVED_STATE_ID &&
      $object->isTargetWorkflowStateAllowed(self::ARCHIVED_STATE_ID)
    ) {
      $access = AccessResult::allowed();
    }
    return $return_as_object ? $access : $access->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?SolutionInterface $solution = NULL): void {
    if (is_null($solution)) {
      return;
    }
    $solution->setWorkflowState(self::ARCHIVED_STATE_ID)->save();
    \Drupal::entityTypeManager()->getStorage($solution->getEntityTypeId())->resetCache();
  }

}
