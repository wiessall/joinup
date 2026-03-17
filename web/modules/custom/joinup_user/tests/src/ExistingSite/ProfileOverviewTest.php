<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_user\ExistingSite;

use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;
use PHPUnit\Framework\Attributes\Group;
use weitzman\LoginTrait\LoginTrait;

/**
 * Tests that a warning is shown on the user profile of users with many groups.
 */
#[Group('joinup_user')]
class ProfileOverviewTest extends JoinupExistingSiteTestBase {

  use LoginTrait;

  /**
   * The owner of the groups.
   */
  protected AccountInterface $owner;

  /**
   * A member of the groups.
   */
  protected AccountInterface $member;

  /**
   * An authenticated user without groups.
   */
  protected AccountInterface $authenticated;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    /** @var \Drupal\og\MembershipManagerInterface $og_membership_manager */
    $og_membership_manager = $this->container->get('og.membership_manager');

    $this->overrideConfig('joinup_user.settings', 'membership_group_limit', 3);

    $this->owner = $this->createUser([], 'some_owner', FALSE, [
      'mail' => "some_user@example.com",
      'pass' => 'plain',
      'roles' => [
        // Making the user a moderator only to avoid dealing with the legal
        // agreements.
        'moderator',
      ],
    ]);
    $this->member = $this->createUser([], 'some_member', FALSE, [
      'mail' => "some_member@example.com",
      'roles' => ['moderator'],
    ]);
    $this->authenticated = $this->createUser([], 'some_user', FALSE, [
      'mail' => "some_user@example.com",
    ]);

    // Sign a user up for more than {membership_group_limit} groups in total.
    // Also, the owner will transparently become a member of all these groups.
    for ($count = 0; $count < 2; $count++) {
      $collection = $this->createNode([
        'type' => 'collection',
        'title' => 'Collection ' . $count,
        'field_state' => 'published',
        'uid' => $this->owner->id(),
      ]);

      $og_membership_manager->createMembership($collection, $this->member)->save();
      $solution = $this->createNode([
        'type' => 'solution',
        'title' => 'Solution ' . $count,
        'og_audience' => $collection,
        'field_state' => 'published',
        'uid' => $this->owner->id(),
      ]);
      $og_membership_manager->createMembership($solution, $this->member)->save();
    }
  }

  /**
   * Tests the warning message for too many groups.
   */
  public function testTooManyGroupsMessage(): void {
    // Assert a user sees the warning when they are a member of too many groups.
    $this->drupalLogin($this->owner);
    $this->drupalGet("/user/{$this->owner->id()}");
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContainsOnce('You have joined a large number of collections and solutions.');
    $this->assertSession()
      ->pageTextContainsOnce('Only the content of the first 3 groups is shown.');

    // Assert the user does not see the same message on another user's profile
    // that is also a member of too many groups.
    $this->drupalGet("/user/{$this->member->id()}");
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextNotContains('You have joined a large number of collections and solutions.');

    // Assert that changing to the member user and visiting their own profile
    // will show the warning (ensure lack of message is not cached).
    $this->drupalLogin($this->member);
    $this->drupalGet("/user/{$this->member->id()}");
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContainsOnce('You have joined a large number of collections and solutions.');

    // Assert that the user that is logged in does not see their message in
    // other profiles without many groups.
    $this->drupalGet("/user/{$this->authenticated->id()}");
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextNotContains('You have joined a large number of collections and solutions.');

    // Assert anonymous users cannot see the message in a profile with many
    // groups.
    $this->drupalLogout();
    $this->drupalGet("/user/{$this->owner->id()}");
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextNotContains('You have joined a large number of collections and solutions.');
  }

}
