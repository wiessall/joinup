<?php

declare(strict_types=1);

namespace Drupal\joinup_achievement;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\flag\FlagServiceInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Awards and revokes flag-backed achievements on user entities.
 *
 * This class is the single source of truth for which flags are achievements
 * and for their display metadata (icon ID and description).
 */
class AchievementService implements AchievementServiceInterface {

  use AutowireTrait;

  /**
   * Constructs an AchievementService object.
   */
  public function __construct(
    #[Autowire(service: 'flag')]
    protected readonly FlagServiceInterface $flagService,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getAchievementFlagIds(): array {
    return array_keys(self::ACHIEVEMENTS);
  }

  /**
   * {@inheritdoc}
   */
  public function getIcon(string $flagId): string {
    return self::ACHIEVEMENTS[$flagId]['icon'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(string $flagId): string {
    return self::ACHIEVEMENTS[$flagId]['description'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function award(string $flagId, UserInterface $user): void {
    $flag = $this->flagService->getFlagById($flagId);
    if ($flag === NULL || $flag->isFlagged($user)) {
      return;
    }
    $this->flagService->flag($flag, $user);
  }

  /**
   * {@inheritdoc}
   */
  public function revoke(string $flagId, UserInterface $user): void {
    $flag = $this->flagService->getFlagById($flagId);
    if ($flag === NULL || !$flag->isFlagged($user)) {
      return;
    }
    $this->flagService->unflag($flag, $user);
  }

  /**
   * {@inheritdoc}
   */
  public function hasAchievement(string $flagId, UserInterface $user): bool {
    $flag = $this->flagService->getFlagById($flagId);
    return $flag !== NULL && (bool) $flag->isFlagged($user);
  }

}
