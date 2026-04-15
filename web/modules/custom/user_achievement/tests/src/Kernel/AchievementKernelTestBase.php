<?php

declare(strict_types=1);

namespace Drupal\Tests\user_achievement\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\UserInterface;
use Drupal\user_achievement\Entity\Achievement;
use Drupal\user_achievement\Entity\AchievementInterface;
use Drupal\user_achievement\Entity\AchievementType;
use Drupal\user_achievement\Entity\AchievementTypeInterface;

/**
 * Provides a shared base for user_achievement kernel tests.
 */
abstract class AchievementKernelTestBase extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'og',
    'options',
    'system',
    'text',
    'user',
    'user_achievement',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('user_achievement');
    $this->installConfig(['system', 'user']);
  }

  /**
   * Creates and saves an AchievementType config entity.
   *
   * @param string $id
   *   The machine name for the type.
   * @param array $values
   *   Optional overrides for label, description, and icon.
   *
   * @return \Drupal\user_achievement\Entity\AchievementTypeInterface
   *   The saved config entity.
   */
  protected function createAchievementType(string $id, array $values = []): AchievementTypeInterface {
    $type = AchievementType::create($values + [
      'id' => $id,
      'label' => ucwords(str_replace('_', ' ', $id)),
      'description' => 'Description for ' . $id,
      'icon' => '',
    ]);
    $type->save();
    return $type;
  }

  /**
   * Creates and saves an Achievement entity for a given user and type.
   *
   * @param string $type
   *   The achievement type (bundle) machine name.
   * @param \Drupal\user\UserInterface $user
   *   The user to link the achievement to.
   *
   * @return \Drupal\user_achievement\Entity\AchievementInterface
   *   The saved achievement entity.
   */
  protected function createAchievement(string $type, UserInterface $user): AchievementInterface {
    $achievement = Achievement::create([
      'type' => $type,
      'uid' => $user->id(),
    ]);
    $achievement->save();
    return $achievement;
  }

}
