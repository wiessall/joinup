<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Gherkin\Node\TableNode;
use Behat\Hook\AfterScenario;
use Behat\Step\Given;
use Drupal\comment\CommentInterface;
use Drupal\comment\Entity\Comment;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\EntityTrait;

/**
 * Behat step definitions for creating comments on content.
 *
 * The discussion context posts comments through the UI, which pins their
 * creation time to "now" and only works for discussions. Ordering by comment
 * date across several bundles cannot be tested that way, so these steps create
 * the comments directly instead.
 *
 * @see \Drupal\joinup\Context\JoinupDiscussionContext::leaveCommentAsUser()
 */
class JoinupCommentContext extends RawDrupalContext {

  use EntityTrait;

  /**
   * Comments created through these steps, to be removed after the scenario.
   *
   * @var \Drupal\comment\CommentInterface[]
   */
  protected array $comments = [];

  /**
   * The comment field of each bundle that accepts comments.
   *
   * Keyed by node bundle, with the comment type as value. Discussions call
   * their comments "replies" and use a field and comment type of their own.
   */
  protected const array COMMENT_FIELDS = [
    'discussion' => ['field_replies', 'reply'],
    'document' => ['field_comments', 'comment'],
    'event' => ['field_comments', 'comment'],
    'news' => ['field_comments', 'comment'],
  ];

  /**
   * Creates comments on existing content.
   *
   * @codingStandardsIgnoreStart
   * Table format:
   *  | content            | author | created             | body            |
   *  | Interesting news   | Alice  | 2026-03-02T10:00:00 | Nice article.   |
   *  | Ongoing discussion | Bob    | 2026-03-04T09:30:00 | I disagree.     |
   * @codingStandardsIgnoreEnd
   *
   * The "author" and "body" columns are optional.
   *
   * @param \Behat\Gherkin\Node\TableNode $table
   *   The comments to create.
   */
  #[Given('the following comments:')]
  public function createComments(TableNode $table): void {
    foreach ($table->getColumnsHash() as $row) {
      $node = $this->getEntityByLabel('node', $row['content']);
      $bundle = $node->bundle();

      if (!isset(self::COMMENT_FIELDS[$bundle])) {
        throw new \RuntimeException(sprintf(
          "The '%s' content '%s' does not accept comments. Commentable bundles: %s.",
          $bundle,
          $row['content'],
          implode(', ', array_keys(self::COMMENT_FIELDS)),
        ));
      }
      [$field_name, $comment_type] = self::COMMENT_FIELDS[$bundle];

      $values = [
        'entity_type' => 'node',
        'entity_id' => $node->id(),
        'field_name' => $field_name,
        'comment_type' => $comment_type,
        'subject' => 'Comment on ' . $node->label(),
        'status' => CommentInterface::PUBLISHED,
        'field_body' => [
          'value' => $row['body'] ?? 'A comment.',
          'format' => 'content_editor',
        ],
      ];

      if (!empty($row['created'])) {
        $values['created'] = strtotime($row['created']);
      }
      if (!empty($row['author'])) {
        $values['uid'] = $this->getUserByName($row['author'])->id();
      }

      $comment = Comment::create($values);
      $comment->save();
      $this->comments[] = $comment;
    }
  }

  /**
   * Deletes the most recent comment on a piece of content.
   *
   * @param string $content
   *   The title of the commented content.
   */
  #[Given('the latest comment on :content is deleted')]
  public function deleteLatestComment(string $content): void {
    $node = $this->getEntityByLabel('node', $content);
    $storage = \Drupal::entityTypeManager()->getStorage('comment');

    // The tie-break on cid mirrors the block, which also lets the later comment
    // win when two share the newest timestamp.
    $cids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('entity_type', 'node')
      ->condition('entity_id', $node->id())
      ->sort('created', 'DESC')
      ->sort('cid', 'DESC')
      ->range(0, 1)
      ->execute();

    if (!$cids) {
      throw new \RuntimeException(sprintf("The content '%s' has no comments.", $content));
    }

    $comment = $storage->load(reset($cids));
    $comment->delete();

    // Forget it, so the cleanup does not delete it twice.
    $this->comments = array_filter(
      $this->comments,
      static fn(CommentInterface $tracked): bool => $tracked->id() !== $comment->id(),
    );
  }

  /**
   * Removes the comments created during the scenario.
   */
  #[AfterScenario]
  public function cleanComments(): void {
    foreach ($this->comments as $comment) {
      $comment->delete();
    }
    $this->comments = [];
  }

  /**
   * Returns a user by name.
   *
   * @param string $name
   *   The user name.
   *
   * @return \Drupal\user\UserInterface
   *   The user.
   */
  protected function getUserByName(string $name): mixed {
    $users = \Drupal::entityTypeManager()
      ->getStorage('user')
      ->loadByProperties(['name' => $name]);

    if (!$users) {
      throw new \RuntimeException(sprintf("No user named '%s'.", $name));
    }

    return reset($users);
  }

}
