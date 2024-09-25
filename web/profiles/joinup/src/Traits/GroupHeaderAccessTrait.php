<?php

declare(strict_types=1);

namespace Drupal\joinup\Traits;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\joinup_group\Entity\GroupInterface;

/**
 * Trait for block plugins we don't want to render on group edit forms.
 */
trait GroupHeaderAccessTrait {

  /**
   * {@inheritdoc}
   */
  public function blockAccess(AccountInterface $account): AccessResultInterface {
    $node = $this->routeMatch->getParameter('node');
    $cond = (!$node instanceof GroupInterface) ||
      $this->routeMatch->getRouteName() !== "entity.{$node->getEntityTypeId()}.edit_form";

    return AccessResult::allowedIf($cond);
  }

}
