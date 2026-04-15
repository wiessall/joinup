<?php

declare(strict_types=1);

namespace Drupal\user_achievement\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user_achievement\Entity\AchievementInterface;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for managing user achievement badges.
 */
class UserAchievementCommands extends DrushCommands {

  /**
   * Constructs a UserAchievementCommands object.
   */
  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct();
  }

  /**
   * Lists all available achievement types (badge definitions).
   *
   * @field-labels
   *   id: ID
   *   label: Label
   *   icon: Icon
   *   description: Description
   * @default-fields id,label,icon,description
   *
   * @command user-achievement:list-types
   * @aliases ua-types
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   *   A table of achievement type records.
   */
  public function listTypes(): RowsOfFields {
    $types = $this->entityTypeManager
      ->getStorage('achievement_type')
      ->loadMultiple();

    $rows = [];
    foreach ($types as $type) {
      $rows[$type->id()] = [
        'id' => $type->id(),
        'label' => $type->label(),
        'icon' => $type->getIcon() ?: '(none)',
        'description' => $type->getDescription(),
      ];
    }

    if (empty($rows)) {
      $this->logger()->warning(dt('No achievement types have been defined yet.'));
    }

    return new RowsOfFields($rows);
  }

  /**
   * Lists all achievements awarded to a user.
   *
   * @param string $user
   *   The user's UID or username.
   *
   * @field-labels
   *   id: Achievement ID
   *   type: Type
   *   label: Badge label
   *   icon: Icon
   *   created: Awarded on
   * @default-fields id,type,label,icon,created
   *
   * @command user-achievement:list
   * @aliases ua-list
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   *   A table of achievement records for the given user.
   */
  public function listForUser(string $user): RowsOfFields {
    $account = $this->loadUser($user);

    $achievements = $this->entityTypeManager
      ->getStorage('user_achievement')
      ->loadByProperties(['uid' => $account->id()]);

    $rows = [];
    foreach ($achievements as $achievement) {
      $type = $this->entityTypeManager
        ->getStorage('achievement_type')
        ->load($achievement->bundle());

      $rows[$achievement->id()] = [
        'id' => $achievement->id(),
        'type' => $achievement->bundle(),
        'label' => $type ? $type->label() : $achievement->bundle(),
        'icon' => $type ? ($type->getIcon() ?: '(none)') : '(none)',
        'created' => date('Y-m-d H:i', (int) $achievement->get('created')->value),
      ];
    }

    if (empty($rows)) {
      $this->logger()->notice(dt('User @user has no achievements.', ['@user' => $account->label()]));
    }

    return new RowsOfFields($rows);
  }

  /**
   * Awards a badge to a user.
   *
   * @param string $user
   *   The user's UID or username.
   * @param string $type
   *   The achievement type machine name (see user-achievement:list-types).
   *
   * @command user-achievement:award
   * @aliases ua-award
   */
  public function award(string $user, string $type): void {
    $account = $this->loadUser($user);

    $achievementType = $this->entityTypeManager
      ->getStorage('achievement_type')
      ->load($type);

    if ($achievementType === NULL) {
      throw new \InvalidArgumentException(dt(
        'Unknown achievement type "@type". Run user-achievement:list-types to see available types.',
        ['@type' => $type],
      ));
    }

    // Prevent duplicate awards of the same badge to the same user.
    $existing = $this->entityTypeManager
      ->getStorage('user_achievement')
      ->loadByProperties(['uid' => $account->id(), 'type' => $type]);

    if (!empty($existing)) {
      $this->logger()->warning(dt(
        'User @user already has the "@type" badge. Skipping.',
        ['@user' => $account->label(), '@type' => $type],
      ));
      return;
    }

    $achievement = $this->entityTypeManager
      ->getStorage('user_achievement')
      ->create(['type' => $type, 'uid' => $account->id()]);
    $achievement->save();

    $this->logger()->success(dt(
      'Awarded the "@type" badge to @user (achievement ID: @id).',
      ['@type' => $type, '@user' => $account->label(), '@id' => $achievement->id()],
    ));
  }

  /**
   * Revokes a badge from a user.
   *
   * @param string $user
   *   The user's UID or username.
   * @param string $type
   *   The achievement type machine name.
   *
   * @command user-achievement:revoke
   * @aliases ua-revoke
   */
  public function revoke(string $user, string $type): void {
    $account = $this->loadUser($user);

    $achievements = $this->entityTypeManager
      ->getStorage('user_achievement')
      ->loadByProperties(['uid' => $account->id(), 'type' => $type]);

    if (empty($achievements)) {
      $this->logger()->warning(dt(
        'User @user does not have the "@type" badge.',
        ['@user' => $account->label(), '@type' => $type],
      ));
      return;
    }

    foreach ($achievements as $achievement) {
      $achievement->delete();
    }

    $this->logger()->success(dt(
      'Revoked the "@type" badge from @user.',
      ['@type' => $type, '@user' => $account->label()],
    ));
  }

  /**
   * Loads a user account by UID or username.
   *
   * @param string $identifier
   *   A numeric UID or a plain username.
   *
   * @return \Drupal\user\UserInterface
   *   The loaded user account.
   *
   * @throws \InvalidArgumentException
   *   When no matching user is found.
   */
  protected function loadUser(string $identifier): \Drupal\user\UserInterface {
    $storage = $this->entityTypeManager->getStorage('user');

    if (is_numeric($identifier)) {
      $account = $storage->load((int) $identifier);
    }
    else {
      $accounts = $storage->loadByProperties(['name' => $identifier]);
      $account = $accounts ? reset($accounts) : NULL;
    }

    if ($account === NULL) {
      throw new \InvalidArgumentException(dt(
        'No user found matching "@identifier".',
        ['@identifier' => $identifier],
      ));
    }

    return $account;
  }

}
