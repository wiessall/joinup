<?php

declare(strict_types=1);

namespace Drupal\Tests\user_achievement\Kernel;

use Drupal\user\Entity\User;
use Drupal\user_achievement\Plugin\Field\FieldType\UserAchievementsFieldItemList;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the computed achievements field on the user entity.
 */
#[CoversClass(UserAchievementsFieldItemList::class)]
#[Group('user_achievement')]
class UserAchievementsComputedFieldTest extends AchievementKernelTestBase {

  /**
   * Tests the computed field is empty when the user has no achievements.
   */
  public function testEmptyForUserWithNoAchievements(): void {
    $user = $this->createUser();
    $this->assertTrue($user->get('achievements')->isEmpty());
  }

  /**
   * Tests that the computed field is populated after achievements are created.
   */
  public function testFieldIsPopulatedAfterAchievementsAreCreated(): void {
    $this->createAchievementType('active_participation');
    $user = $this->createUser();

    $achievement = $this->createAchievement('active_participation', $user);

    // Reload the user so the computed field starts uncached.
    $user = User::load($user->id());
    $field = $user->get('achievements');

    $this->assertFalse($field->isEmpty());
    $this->assertCount(1, $field);
    $this->assertEquals($achievement->id(), $field->get(0)->target_id);
  }

  /**
   * Tests that achievements belonging to another user are not included.
   */
  public function testFieldOnlyIncludesOwnAchievements(): void {
    $this->createAchievementType('active_participation');
    $user = $this->createUser();
    $otherUser = $this->createUser();

    $this->createAchievement('active_participation', $user);
    $this->createAchievement('active_participation', $otherUser);

    $user = User::load($user->id());
    $this->assertCount(1, $user->get('achievements'));
  }

  /**
   * Tests that the computed field items are sorted alphabetically by bundle.
   */
  public function testFieldItemsAreSortedByBundle(): void {
    $this->createAchievementType('learning_achievements');
    $this->createAchievementType('active_participation');
    $this->createAchievementType('community_membership');
    $user = $this->createUser();

    // Create achievements in non-alphabetical order.
    $this->createAchievement('learning_achievements', $user);
    $this->createAchievement('community_membership', $user);
    $this->createAchievement('active_participation', $user);

    $user = User::load($user->id());
    $field = $user->get('achievements');

    $this->assertCount(3, $field);
    $bundles = [];
    foreach ($field as $item) {
      $bundles[] = $item->entity->bundle();
    }
    $this->assertEquals(
      ['active_participation', 'community_membership', 'learning_achievements'],
      $bundles,
    );
  }

  /**
   * Tests that the field is empty for an unsaved (new) user.
   */
  public function testFieldIsEmptyForNewUser(): void {
    $user = User::create(['name' => 'unsaved', 'status' => 1]);
    // Do not save — the entity has no ID.
    $this->assertTrue($user->get('achievements')->isEmpty());
  }

  /**
   * Tests that the field definition is correctly attached to the user entity.
   */
  public function testFieldIsDefinedOnUserEntity(): void {
    $definitions = \Drupal::service('entity_field.manager')
      ->getBaseFieldDefinitions('user');

    $this->assertArrayHasKey('achievements', $definitions);
    $this->assertEquals('entity_reference', $definitions['achievements']->getType());
    $this->assertTrue($definitions['achievements']->isComputed());
    $this->assertFalse($definitions['achievements']->isDisplayConfigurable('form'));
    $this->assertTrue($definitions['achievements']->isDisplayConfigurable('view'));
  }

}
