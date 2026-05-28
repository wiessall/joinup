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
   * Returns the badge flag entities ordered by their weight.
   *
   * @return array<string, \Drupal\flag\FlagInterface>
   *   List of badge flag entities, keyed by ID and ordered by weight.
   */
  public function getBadgeFlags(): array {
    $badges = array_filter(
      $this->flagService->getAllFlags('user'),
      fn(FlagInterface $flag): bool => $flag->getThirdPartySetting('joinup_badge', 'icon') && $flag->getThirdPartySetting('joinup_badge', 'description'),
    );
    uasort($badges, fn(FlagInterface $a, FlagInterface $b): int => $a->getWeight() <=> $b->getWeight());

    return $badges;
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
    if ($flag && !$flag->isFlagged($user)) {
      $this->flagService->flag($flag, $user);
    }
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
    if ($flag?->isFlagged($user)) {
      $this->flagService->unflag($flag, $user);
    }
  }

}
