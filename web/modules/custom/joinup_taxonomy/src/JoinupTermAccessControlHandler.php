<?php

declare(strict_types=1);

namespace Drupal\joinup_taxonomy;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\taxonomy\TermAccessControlHandler;

/**
 * Term access handler that opts 'tags' into the 'view label' operation.
 */
class JoinupTermAccessControlHandler extends TermAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected $viewLabelOperation = TRUE;

  /**
   * {@inheritdoc}
   */
  public function access(EntityInterface $entity, $operation, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    if ($operation === 'view label' && $entity->bundle() !== 'tags') {
      $operation = 'view';
    }
    return parent::access($entity, $operation, $account, $return_as_object);
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if ($operation === 'view label' && $entity->bundle() === 'tags') {
      return parent::checkAccess($entity, 'view', $account);
    }
    return parent::checkAccess($entity, $operation, $account);
  }

}
