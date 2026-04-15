<?php

declare(strict_types=1);

namespace Drupal\Tests\user_achievement\Kernel;

use Drupal\user_achievement\Entity\Achievement;
use Drupal\user_achievement\Entity\AchievementInterface;
use Drupal\user_achievement\Entity\AchievementType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the Achievement content entity and AchievementType config entity.
 */
#[CoversClass(Achievement::class)]
#[CoversClass(AchievementType::class)]
#[Group('user_achievement')]
class AchievementEntityTest extends AchievementKernelTestBase {

  /**
   * Tests that an achievement can be created and loaded by ID.
   */
  public function testCreateAndLoad(): void {
    $this->createAchievementType('active_participation');
    $user = $this->createUser();

    $achievement = $this->createAchievement('active_participation', $user);
    $id = $achievement->id();

    $loaded = Achievement::load($id);
    $this->assertInstanceOf(AchievementInterface::class, $loaded);
    $this->assertEquals('active_participation', $loaded->bundle());
    $this->assertEquals($user->id(), $loaded->getOwnerId());
  }

  /**
   * Tests that the created timestamp is set on save.
   */
  public function testCreatedTimestampIsSet(): void {
    $this->createAchievementType('active_participation');
    $user = $this->createUser();

    $before = \Drupal::time()->getRequestTime();
    $achievement = $this->createAchievement('active_participation', $user);
    $after = \Drupal::time()->getRequestTime();

    $created = (int) $achievement->get('created')->value;
    $this->assertGreaterThanOrEqual($before, $created);
    $this->assertLessThanOrEqual($after, $created);
  }

  /**
   * Tests that an achievement can be deleted.
   */
  public function testDelete(): void {
    $this->createAchievementType('active_participation');
    $user = $this->createUser();

    $achievement = $this->createAchievement('active_participation', $user);
    $id = $achievement->id();
    $achievement->delete();

    $this->assertNull(Achievement::load($id));
  }

  /**
   * Tests that multiple achievement types can each have their own achievement.
   */
  public function testMultipleBundles(): void {
    $this->createAchievementType('active_participation');
    $this->createAchievementType('community_membership');
    $user = $this->createUser();

    $a1 = $this->createAchievement('active_participation', $user);
    $a2 = $this->createAchievement('community_membership', $user);

    $this->assertEquals('active_participation', Achievement::load($a1->id())->bundle());
    $this->assertEquals('community_membership', Achievement::load($a2->id())->bundle());
  }

  /**
   * Tests that an AchievementType stores and exposes its fields correctly.
   */
  public function testAchievementTypeFields(): void {
    $type = $this->createAchievementType('learning_achievements', [
      'label' => 'Learning Achievements',
      'description' => 'This user has completed three or more Interoperable Europe Academy courses.',
      'icon' => 'award',
    ]);

    $this->assertEquals('Learning Achievements', $type->label());
    $this->assertEquals(
      'This user has completed three or more Interoperable Europe Academy courses.',
      $type->getDescription()
    );
    $this->assertEquals('award', $type->getIcon());
  }

  /**
   * Tests that setIcon() correctly updates the stored icon ID.
   */
  public function testSetIcon(): void {
    $type = $this->createAchievementType('active_participation', ['icon' => 'award']);

    $type->setIcon('patch-check');

    $this->assertEquals('patch-check', $type->getIcon());
  }

  /**
   * Tests that AchievementType is the bundle_of user_achievement.
   */
  public function testAchievementTypeIsBundleOf(): void {
    $entity_type = \Drupal::entityTypeManager()->getDefinition('achievement_type');
    $this->assertEquals('user_achievement', $entity_type->get('bundle_of'));
  }

}
