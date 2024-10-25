<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\EntityTrait;
use Drupal\joinup_discussion\Entity\DiscussionInterface;
use Drupal\joinup_subscription\JoinupDiscussionSubscriptionInterface;
use PHPUnit\Framework\Assert;

/**
 * Behat step definitions for testing discussions.
 */
class JoinupDiscussionContext extends RawDrupalContext {

  use EntityTrait;

  /**
   * Navigates to the canonical page display of a discussion.
   *
   * @param string $title
   *   The name of the discussion.
   *
   * @When I go to the :title discussion
   * @When I visit the :title discussion
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function visitDiscussion(string $title): void {
    /** @var \Drupal\node\Entity\Node $node */
    $node = $this->getEntityByLabel('node', $title, 'discussion');
    $this->visitPath($node->toUrl()->toString());
  }

  /**
   * Checks that the given discussion has an expected number of subscribers.
   *
   * @param string $title
   *   The discussion title.
   * @param int $count
   *   The expected number of subscribers.
   *
   * @Then the :title discussion should have :count subscriber(s)
   */
  public function assertSubscribers(string $title, int $count): void {
    $discussion = $this->getEntityByLabel('node', $title, 'discussion');
    $subscribers = $this->getDiscussionSubscriptionService()->getSubscribers($discussion, 'subscribe_discussions');
    Assert::assertEquals($count, count($subscribers));
  }

  /**
   * Returns the Portal subscription service.
   *
   * @return \Drupal\joinup_subscription\JoinupDiscussionSubscriptionInterface
   *   The subscription service.
   */
  protected function getDiscussionSubscriptionService(): JoinupDiscussionSubscriptionInterface {
    return \Drupal::service('joinup_subscription.discussion_subscription');
  }

  /**
   * Leaves a comment at the given discussion on behalf of the given user.
   *
   * This step definition also switches back to the original user account at the
   * end of the task and returns to the original page.
   *
   * @param string $userName
   *   The username of the account to own the new comment.
   * @param string $discussionLabel
   *   The label of the discussion where the comment should be posted.
   *
   * @Given the :userName user leaves a comment at the :discussionLabel discussion
   */
  public function leaveCommentAsUser(string $userName, string $discussionLabel): void {
    $session = $this->getSession();
    $userManager = $this->getUserManager();
    $authenticationManager = $this->getAuthenticationManager();
    $originalUrl = str_replace(
      $this->getMinkParameter('base_url'),
      '',
      $session->getCurrentUrl(),
    );

    $commentMessage = 'Hello!';
    $userBeforeCommenting = $userManager->getCurrentUser();
    $userManager->setCurrentUser($user = $userManager->getUser($userName));
    $authenticationManager->logIn($user);
    $discussion = $this->getEntityByLabel('node', $discussionLabel, 'discussion');
    Assert::assertInstanceOf(
      DiscussionInterface::class,
      $discussion,
      sprintf("Cannot find the discussion with label '%s'", $discussionLabel),
    );
    $this->visitPath($discussion->toUrl()->toString());
    $session->getPage()->fillField('Create comment', $commentMessage);
    $session->getPage()->pressButton('Post comment');
    $userManager->setCurrentUser($userBeforeCommenting);
    $authenticationManager->logOut();
    if ($userBeforeCommenting) {
      $authenticationManager->logIn($userBeforeCommenting);
    }
    $this->visitPath($originalUrl);
  }

}
