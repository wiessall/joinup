<?php

declare(strict_types = 1);

namespace Drupal\joinup_group_content_management\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;

/**
 * Provides a VBO action that archive content.
 *
 * @Action(
 *   id = "joinup_archive",
 *   label = @Translation("Archive content"),
 *   type = "node"
 * )
 */
class ArchiveAction extends ViewsBulkOperationsActionBase {


  /**
   * {@inheritdoc}
   */
  public function access($node, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    // The access is limited at the view level.
    return $return_as_object ? AccessResult::allowed() : TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?NodeInterface $node = NULL): void {
  }

}
