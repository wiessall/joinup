<?php

declare(strict_types=1);

namespace Drupal\joinup_rss\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\node\NodeInterface;

/**
 * Provide additional access checks for the collection feed view.
 */
class GroupFeedController extends ControllerBase {

  /**
   * Access check for the group feed view route.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The encoded ID of an RDF entity.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result object.
   */
  public function access(NodeInterface $node): AccessResultInterface {
    return AccessResult::allowedIf($node instanceof GroupInterface && $node->isPublished())
      ->addCacheableDependency($node);
  }

}
