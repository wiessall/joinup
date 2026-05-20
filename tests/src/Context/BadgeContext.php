<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Mink\Exception\ExpectationException;
use Behat\Step\Given;
use Behat\Step\Then;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\TraversingTrait;
use Drupal\joinup\Traits\UserTrait;
use Drupal\joinup_badge\PortalContributorBadgeEvaluator;

/**
 * Behat step definitions for the Joinup badges feature.
 */
class BadgeContext extends RawDrupalContext {

  use UserTrait;
  use TraversingTrait;

  /**
   * Asserts that a user has been awarded a given badge.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   Thrown when the badge is not held by the user.
   */
  #[Then('user :name should have the :badge badge')]
  public function assertUserHasBadge(string $name, string $badge): void {
    if (!$this->isBadgeFlagged($name, $badge)) {
      throw new ExpectationException(
        sprintf('User "%s" was expected to have the "%s" badge but does not.', $name, $badge),
        $this->getSession()->getDriver(),
      );
    }
  }

  /**
   * Asserts that a user has not been awarded a given badge.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   Thrown when the badge is held by the user.
   */
  #[Then('user :name should not have the :badge badge')]
  public function assertUserDoesNotHaveBadge(string $name, string $badge): void {
    if ($this->isBadgeFlagged($name, $badge)) {
      throw new ExpectationException(
        sprintf('User "%s" was expected not to have the "%s" badge but does.', $name, $badge),
        $this->getSession()->getDriver(),
      );
    }
  }

  /**
   * Forces the asynchronous pipeline of a given badge to run synchronously.
   *
   * @throws \InvalidArgumentException
   *   When the given badge has no eligibility pipeline registered.
   */
  #[Given('the eligibility queue of :badge badge is processed')]
  public function processBadgeEligibilityQueue(string $badge): void {
    match ($badge) {
      PortalContributorBadgeEvaluator::FLAG_ID => $this->processPortalContributorWork(),
      default => throw new \InvalidArgumentException(sprintf('No eligibility pipeline is registered for the "%s" badge.', $badge)),
    };
  }

  /**
   * Returns whether the named user is currently flagged with the badge.
   */
  protected function isBadgeFlagged(string $name, string $badge): bool {
    $user = $this->getUserByName($name);
    /** @var \Drupal\flag\FlagServiceInterface $flagService */
    $flagService = \Drupal::service('flag');
    $flag = $flagService->getFlagById($badge);
    if (!$flag) {
      throw new \InvalidArgumentException(sprintf('The "%s" badge flag does not exist.', $badge));
    }
    return $flag->isFlagged($user);
  }

  /**
   * Per-badge handler for the 'portal_contributor' badge.
   */
  private function processPortalContributorWork(): void {
    // Pass 0 to scan all content regardless of age, so test fixtures
    // created with arbitrary `created` timestamps are picked up.
    \Drupal::service(PortalContributorBadgeEvaluator::class)->enqueueRecentAuthors(0);

    $queueId = PortalContributorBadgeEvaluator::QUEUE_ID;
    $queue = \Drupal::queue($queueId);
    /** @var \Drupal\Core\Queue\QueueWorkerInterface $worker */
    $worker = \Drupal::service('plugin.manager.queue_worker')->createInstance($queueId);
    while ($item = $queue->claimItem()) {
      $worker->processItem($item->data);
      $queue->deleteItem($item);
    }
  }

  /**
   * Asserts that badges are present in tile.
   *
   * @param string $badges
   *   A comma separated list of badges.
   * @param string $heading
   *   The heading of the tile.
   *
   * @throws \Exception
   *   Thrown when the expected badges are not found in the tile.
   */
  #[Then('I (should )see badge(s) :badges in the :heading tile')]
  public function assertBadgesInTile(string $badges, string $heading): void {
    $badges = array_map('trim', explode(',', $badges));

    $element = $this->getTileByHeading($heading);

    foreach ($badges as $badge) {
      $badgeClass = '.user-badge--' . $badge;

      if (!$element->find('css', $badgeClass)) {
        throw new \Exception(sprintf(
          'Badge "%s" was not found in the "%s" tile.',
          $badge,
          $heading
        ));
      }
    }
  }

}
