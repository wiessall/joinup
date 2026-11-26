<?php

declare(strict_types=1);

namespace Drupal\sti_dedupe\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\og\OgAccessInterface;

/**
 * Site-wide reviewers, or members whose collection role has the group permission.
 */
final class ReviewAccessCheck implements AccessInterface {

  public function __construct(private readonly OgAccessInterface $ogAccess) {}

  public function access(RouteMatchInterface $route_match, AccountInterface $account): AccessResultInterface {
    $site = AccessResult::allowedIfHasPermission($account, 'review sti duplicates');
    $collection = $route_match->getParameter('collection') ?? $route_match->getParameter('node');
    if ($site->isAllowed() || !$collection instanceof EntityInterface) {
      return $site;
    }
    return $site->orIf($this->ogAccess->userAccess($collection, 'review sti duplicates', $account));
  }

}
