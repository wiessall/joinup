<?php

declare(strict_types=1);

namespace Drupal\user_achievement\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\node\NodeInterface;
use Drupal\og\OgMembershipInterface;

/**
 * Hooks for the user_achievement module.
 */
class AchievementHooks {

  /**
   * Implements hook_node_insert().
   *
   * Triggered when a new node is created. Used to evaluate whether the author
   * has met the criteria for the Active Participation badge (at least 5
   * published content items within a 2-month period).
   */
  #[Hook('node_insert')]
  public function nodeInsert(NodeInterface $node): void {
    // @todo Implement Active Participation badge allocation logic.
  }

  /**
   * Implements hook_og_membership_insert().
   *
   * Triggered when a new OG membership is created. Used to evaluate whether
   * the user qualifies for the Interoperable Europe Community Membership badge
   * upon becoming an approved member of the community.
   */
  #[Hook('og_membership_insert')]
  public function ogMembershipInsert(OgMembershipInterface $ogMembership): void {
    // @todo Implement Interoperable Europe Community Membership badge allocation logic.
  }

}
