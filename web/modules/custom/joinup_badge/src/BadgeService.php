<?php

declare(strict_types=1);

namespace Drupal\joinup_badge;

use Drupal\flag\FlagInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Awards and revokes flag-backed badges on user entities.
 */
readonly class BadgeService {

  public function __construct(
    #[Autowire(service: 'flag')]
    protected FlagServiceInterface $flagService,
  ) {}

  /**
   * Returns the badge flag entities.
   *
   * @return array<string, \Drupal\flag\FlagInterface>
   *   List of badge flag entities, keyed by their ID.
   */
  public function getBadgeFlags(): array {
    return array_filter(
      $this->flagService->getAllFlags('user'),
      fn(FlagInterface $flag): bool => $flag->getThirdPartySetting('joinup_badge', 'icon') && $flag->getThirdPartySetting('joinup_badge', 'description'),
    );
  }

  /**
   * Awards a badge to a user by flagging the user entity.
   *
   * If the user already holds the badge, this is a no-op.
   *
   * @param string $flagId
   *   The machine name of the badge flag (e.g. 'iec_member').
   * @param \Drupal\user\UserInterface $user
   *   The user to award the badge to.
   */
  public function award(string $flagId, UserInterface $user): void {
    $flag = $this->flagService->getFlagById($flagId);
    if ($flag === NULL || $flag->isFlagged($user)) {
      return;
    }
    $this->flagService->flag($flag, $user);
  }

  /**
   * Revokes a badge from a user by unflagging the user entity.
   *
   * If the user does not hold the badge, this is a no-op.
   *
   * @param string $flagId
   *   The machine name of the badge flag (e.g. 'iec_member').
   * @param \Drupal\user\UserInterface $user
   *   The user to revoke the badge from.
   */
  public function revoke(string $flagId, UserInterface $user): void {
    $flag = $this->flagService->getFlagById($flagId);
    if ($flag === NULL || !$flag->isFlagged($user)) {
      return;
    }
    $this->flagService->unflag($flag, $user);
  }

  /**
   * Checks whether a user currently holds a given badge.
   *
   * @param string $flagId
   *   The machine name of the badge flag (e.g. 'iec_member').
   * @param \Drupal\user\UserInterface $user
   *   The user to check.
   *
   * @return bool
   *   TRUE if the user holds the badge, FALSE otherwise.
   */
  public function hasBadge(string $flagId, UserInterface $user): bool {
    $flag = $this->flagService->getFlagById($flagId);
    return $flag !== NULL && (bool) $flag->isFlagged($user);
  }

}
