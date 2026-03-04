<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_user\ExistingSite;

use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;
use Drupal\joinup_collection\JoinupCollectionHelper;
use Drupal\joinup_community_content\CommunityContentHelper;
use Drupal\joinup_user\JoinupUserDeletedInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\node\NodeStorageInterface;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 *
 */
#[CoversClass(\Drupal\joinup_user\JoinupUserDeleted::class)]
#[CoversClass(\joinup_user_user_access::class)]
#[Group('joinup_user')]
class DeletedUserTest extends JoinupExistingSiteTestBase {

  /**
   * Testing entities.
   *
   * @var array<int|string, \Drupal\joinup_community_content\Entity\CommunityContentInterface>
   */
  protected array $nodes = [];

  /**
   *
   * @param array $initialName
   *   Initial username, first name, family name, expected full name.
   * @param array $updatedName
   *   Updated username, first name, family name, expected full name.
   */
  #[DataProvider('fullNameCases')]
  public function testFullName(array $initialName, array $updatedName): void {
    [$username, $firstName, $familyName, $expectedFullName] = $initialName;
    $account = $this->createUser([], $username, FALSE, [
      'field_user_first_name' => $firstName,
      'field_user_family_name' => $familyName,
    ]);
    $this->assertSame($expectedFullName, $account->getDisplayName());
    $this->assertAuthoredContent($account, FALSE);
    $this->assertDeletedUserNoAuthoredContent($account, $expectedFullName);

    [$username, $firstName, $familyName, $expectedFullName] = $updatedName;
    $account
      ->set('field_user_first_name', $firstName)
      ->set('field_user_family_name', $familyName);
    if ($username) {
      $account->set('name', $username);
    }
    $account->save();
    $this->assertSame($expectedFullName, $account->getDisplayName());
    $this->assertAuthoredContent($account, FALSE);
    $this->assertDeletedUserNoAuthoredContent($account, $expectedFullName);

    $account->delete();
    \Drupal::getContainer()->get('joinup_user.deleted')->destruct();

    $this->assertAuthoredContent($account, TRUE);
    $this->assertDeletedUserAuthoredContent($account, $expectedFullName);

    // Cannot access a deleted use pseudo-account.
    $deletedAccount = \Drupal::getContainer()
      ->get('joinup_user.deleted')
      ->getDeletedUserPseudoAccount($account->id());
    $this->assertInstanceOf(UserInterface::class, $deletedAccount);
    $this->assertSame($expectedFullName, $deletedAccount->getDisplayName());

    $this->assertFalse($deletedAccount->access('view'));
    $this->assertFalse($deletedAccount->access('update'));
    $this->assertFalse($deletedAccount->access('delete'));

    // Test that tables are cleaned when authored content is removed.
    while ($this->nodes) {
      $entity = array_shift($this->nodes);
      $entity->delete();
      if ($this->nodes) {
        // There are still other entities authored by the user.
        $this->assertDeletedUserAuthoredContent($account, $expectedFullName);
      }
      else {
        // The latest entity authored by the user has been deleted.
        $this->assertDeletedUserNoAuthoredContent($account, $expectedFullName);
      }
    }
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
   * Asserts that author is correctly rendered on content.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   * @param bool $deleted
   *   Whether the author account has been deleted.
   */
  protected function assertAuthoredContent(AccountInterface $account, bool $deleted): void {
    if (!$this->nodes) {
      // Testing entities don't exist yet. Create them.
      $collection = \Drupal::getContainer()->get('rdf_sync.mapper')
        ->getEntityByUri(JoinupCollectionHelper::JOINUP_COLLECTION_URI);

      foreach (CommunityContentHelper::BUNDLES as $bundle) {
        $node = $this->createNode([
          'type' => $bundle,
          'title' => $this->randomString(),
          'og_audience' => $collection,
          'field_state' => 'published',
          'uid' => $account->id(),
          'skip_notification' => TRUE,
        ]);

        // Create a new revision.
        $node->setNewRevision();
        $node->set('title', $this->randomString());
        $node->save();

        $this->nodes[$node->id()] = $node;
      }
    }
    else {
      // Reload entities.
      foreach ($this->nodes as $id => $node) {
        $this->nodes[$id] = Node::load($id);
      }
    }

    $assert = $this->assertSession();
    $fullName = $account->getDisplayName();

    foreach ($this->nodes as $node) {
      $this->drupalGet($node->toUrl());

      if (!$deleted) {
        // Check entity author.
        $this->assertAuthor($node, $account->id());

        // Author is linked.
        $assert->linkExists($fullName);
      }
      else {
        // Check entity author.
        $this->assertAuthor($node, 0);

        // The author is displayed but not linked.
        $assert->linkNotExists($fullName);
        $assert->pageTextContains($fullName);
      }
    }
  }

  /**
   * Asserts that a node and its revision logs are authored by a user.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   * @param int|string $expectedUid
   *   The ID of the expected author.
   */
  protected function assertAuthor(NodeInterface $node, int|string $expectedUid): void {
    $this->assertSame((int) $expectedUid, (int) $node->getOwnerId());

    $storage = \Drupal::entityTypeManager()->getStorage('node');
    assert($storage instanceof NodeStorageInterface);
    $query = $storage->getQuery()->allRevisions()->condition('nid', $node->id())->accessCheck(FALSE);
    $revision_ids = array_keys($query->execute());
    foreach ($storage->loadMultipleRevisions($revision_ids) as $revision) {
      $this->assertSame((int) $expectedUid, (int) $revision->getRevisionUserId());
    }
  }

  /**
   * Asserts that a deleted user has been registered.
   *
   * @param \Drupal\user\UserInterface $account
   *   The user account.
   * @param string $fullName
   *   The user's full name.
   */
  protected function assertDeletedUserAuthoredContent(UserInterface $account, string $fullName): void {
    $this->deletedUserAuthoredContentHelper($account, $fullName, 1);
  }

  /**
   * Asserts that a user is not registered as deleted.
   *
   * @param \Drupal\user\UserInterface $account
   *   A deleted user account.
   * @param string $fullName
   *   The user's full name.
   */
  protected function assertDeletedUserNoAuthoredContent(UserInterface $account, string $fullName): void {
    $this->deletedUserAuthoredContentHelper($account, $fullName, 0);
  }

  /**
   * Provides a helper method for deleted user account registration.
   *
   * @param \Drupal\user\UserInterface $account
   *   The user account.
   * @param string $fullName
   *   The user full name.
   * @param int $expectedCount
   *   Number of expected occurrences in the deleted account tables.
   */
  protected function deletedUserAuthoredContentHelper(UserInterface $account, string $fullName, int $expectedCount): void {
    $db = \Drupal::database();
    $found = (int) $db->select(JoinupUserDeletedInterface::TABLE_USER)
      ->condition('uid', $account->id())
      ->condition('full_name', $fullName)
      ->countQuery()
      ->execute()
      ->fetchField();
    $this->assertSame($expectedCount, $found);
    foreach ($this->nodes as $entity) {
      $found = (int) $db->select(JoinupUserDeletedInterface::TABLE_USER_CONTENT)
        ->condition('uid', $account->id())
        ->condition('entity_type', $entity->getEntityTypeId())
        ->condition('entity_id', $entity->id())
        ->countQuery()
        ->execute()
        ->fetchField();
      $this->assertSame($expectedCount, $found);
    }
  }

}
