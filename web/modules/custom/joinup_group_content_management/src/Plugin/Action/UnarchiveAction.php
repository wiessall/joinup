<?php

declare(strict_types = 1);

namespace Drupal\joinup_group_content_management\Plugin\Action;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;

/**
 * Provides a VBO action that unarchive content.
 *
 * @Action(
 *   id = "joinup_unarchive",
 *   label = @Translation("Unarchive content"),
 *   type = "node"
 * )
 */
class UnarchiveAction extends ViewsBulkOperationsActionBase {


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
  public function execute(?NodeInterface $node = NULL): MarkupInterface {
    return $this->t('Unarchived');
  }

}
