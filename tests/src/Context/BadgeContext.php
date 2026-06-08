<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Mink\Exception\ExpectationException;
use Behat\Step\Given;
use Behat\Step\Then;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\EntityTrait;
use Drupal\joinup\Traits\TraversingTrait;
use Drupal\joinup\Traits\UserTrait;
use Drupal\joinup_badge\BadgeService;
use Drupal\joinup_badge\Hook\BadgeHooks;

/**
 * Behat step definitions for the Joinup badges feature.
 */
class BadgeContext extends RawDrupalContext {

  use UserTrait;
  use TraversingTrait;
  use EntityTrait;

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
   * Awards a badge to a user.
   */
  #[Given('the :badge badge is awarded to :name')]
  public function awardBadgeToUser(string $badge, string $name): void {
    \Drupal::service(BadgeService::class)->award($badge, $this->getUserByName($name));
  }

  /**
   * Runs pending badge evaluations that fire on kernel.terminate.
   *
   * @throws \InvalidArgumentException
   *   When the given badge has no evaluator registered.
   */
  #[Given('pending :badge badge evaluations are run')]
  public function runPendingBadgeEvaluations(string $badge): void {
    match ($badge) {
      'portal_contributor' => \Drupal::service(BadgeHooks::class)->destruct(),
      default => throw new \InvalidArgumentException(sprintf('Unknown badge "%s"; add a match arm to %s::runPendingBadgeEvaluations() to teach Behat how to evaluate it.', $badge, self::class)),
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

  /**
   * Asserts that certificate tile has the logo properly set.
   *
   * @param string $logo
   *   The logo file to inspect.
   * @param string $title
   *   The title of tile to inspect.
   *
   * @throws \Exception
   *   Thrown when the logo is not found or not correct.
   */
  #[Then('the logo :logo should be shown in the :title certificate tile')]
  public function assertCertificateTileHasLogo(string $logo, string $title): void {
    // Retrieve the URL of the logo in the tile.
    $tile = $this->getTileByHeading($title);
    $actual_url = $tile->find('xpath', '//img')->getAttribute('src');
    if (!$actual_url) {
      throw new \Exception("No logo link found in the '$title' tile.");
    }

    $solution = $this->getEntityByLabel('node', $title, 'solution');

    $image = $solution->getLogoAsFile();
    $expected_url = \Drupal::service('file_url_generator')->generateAbsoluteString($image->getFileUri());

    $this->assertSameValues($expected_url, $actual_url, 'The logo shown is not the correct one.');
  }

}
