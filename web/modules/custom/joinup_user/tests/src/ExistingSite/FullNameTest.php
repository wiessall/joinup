<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_user\ExistingSite;

use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;
use Drupal\joinup_user\JoinupUserDeletedUserInterface;
use Drupal\user\UserInterface;

/**
 * @coversDefaultClass \Drupal\joinup_user\JoinupUserDeletedUser
 * @group joinup_user
 */
class FullNameTest extends JoinupExistingSiteTestBase {

  /**
   * @covers ::onUserInsert
   * @covers ::onUserUpdate
   * @covers ::onUserDelete
   * @covers ::getFullName
   * @covers ::isUserDeleted
   * @covers ::getDeletedUser
   * @covers \joinup_user_user_access
   * @dataProvider fullNameCases
   *
   * @param array $initialName
   *   Initial username, first name, family name, expected full name.
   * @param array $updatedName
   *   Updated username, first name, family name, expected full name.
   */
  public function testFullName(array $initialName, array $updatedName): void {
    [$username, $firstName, $familyName, $expectedFullName] = $initialName;
    $account = $this->createUser([], $username, FALSE, [
      'field_user_first_name' => $firstName,
      'field_user_family_name' => $familyName,
    ]);
    $this->assertSame($expectedFullName, $account->getDisplayName());
    $this->assertFullNameStoredForExistingAccount($account, $expectedFullName);

    [$username, $firstName, $familyName, $expectedFullName] = $updatedName;
    $account
      ->set('field_user_first_name', $firstName)
      ->set('field_user_family_name', $familyName);
    if ($username) {
      $account->set('name', $username);
    }
    $account->save();

    $this->assertSame($expectedFullName, $account->getDisplayName());
    $this->assertFullNameStoredForExistingAccount($account, $expectedFullName);

    $account->delete();
    $this->assertFullNameStoredForDeletedAccount($account, $expectedFullName);

    $deletedAccount = \Drupal::getContainer()
      ->get('joinup_user.deleted_user')
      ->getDeletedUser($account->id());
    $this->assertInstanceOf(UserInterface::class, $deletedAccount);
    $this->assertSame($expectedFullName, $deletedAccount->getDisplayName());

    $this->assertFalse($deletedAccount->access('view'));
    $this->assertFalse($deletedAccount->access('update'));
    $this->assertFalse($deletedAccount->access('delete'));
  }

  /**
   * Provides test cases for self::testFullName().
   *
   * @return array[][]
   *   List of test cases. Each case is a tuple of arrays. The first element is
   *   the initial name values, the second element represents the updated name
   *   values. Both are arrays with four elements: username, first name, family
   *   name and expected full name.
   *
   * @see self::testFullName()
   */
  public static function fullNameCases(): array {
    return [
      'none' => [
        ['get a name', NULL, NULL, 'get a name'],
        ['changed name', NULL, NULL, 'changed name'],
      ],
      'first name only' => [
        [NULL, 'John', NULL, 'John'],
        [NULL, 'Nathaniel', NULL, 'Nathaniel'],
      ],
      'family name only' => [
        [NULL, NULL, 'Doe', 'Doe'],
        [NULL, NULL, 'Van Halen', 'Van Halen'],
      ],
      'both' => [
        [NULL, 'John', 'Doe', 'John Doe'],
        [NULL, 'Eddie', 'Van Halen', 'Eddie Van Halen'],
      ],
    ];
  }

  /**
   * Asserts that an existing user full name is stored in the table.
   *
   * @param \Drupal\user\UserInterface $user
   *   An existing user account.
   * @param string $fullName
   *   The user's full name.
   */
  protected function assertFullNameStoredForExistingAccount(UserInterface $user, string $fullName): void {
    $this->storedFullnNameHelper((int) $user->id(), $fullName, FALSE);
  }

  /**
   * Asserts that a deleted user full name is stored in the table.
   *
   * @param \Drupal\user\UserInterface $user
   *   An deleted user account.
   * @param string $fullName
   *   The user's full name.
   */
  protected function assertFullNameStoredForDeletedAccount(UserInterface $user, string $fullName): void {
    $this->storedFullnNameHelper((int) $user->id(), $fullName, TRUE);
  }

  /**
   * Provides a helper method for custom assertions.
   *
   * @param int $uid
   *   The user ID.
   * @param string $fullName
   *   The user full name.
   * @param bool $deleted
   *   Whether the user has been deleted.
   */
  protected function storedFullnNameHelper(int $uid, string $fullName, bool $deleted): void {
    $record = \Drupal::database()->select(JoinupUserDeletedUserInterface::TABLE)
      ->fields(JoinupUserDeletedUserInterface::TABLE)
      ->condition('uid', $uid)
      ->condition('full_name', $fullName)
      ->condition('deleted', $deleted)
      ->execute()
      ->fetch();
    $this->assertNotFalse($record);
  }

}
