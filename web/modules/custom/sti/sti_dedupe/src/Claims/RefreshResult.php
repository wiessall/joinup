<?php

declare(strict_types=1);

namespace Drupal\sti_dedupe\Claims;

/**
 * What one conflict refresh changed on one innovation.
 */
final class RefreshResult {

  /**
   * Constructs a RefreshResult.
   *
   * @param int $created
   *   New pending resolutions for newly conflicting properties.
   * @param int $updated
   *   Pending resolutions whose list of claimed values changed.
   * @param int $staled
   *   Curator rulings whose claims changed since they were made.
   * @param int $removed
   *   Pending resolutions dropped because the sources now agree.
   */
  public function __construct(
    public readonly int $created = 0,
    public readonly int $updated = 0,
    public readonly int $staled = 0,
    public readonly int $removed = 0,
  ) {}

  /**
   * Whether anything changed.
   */
  public function changed(): bool {
    return ($this->created + $this->updated + $this->staled + $this->removed) > 0;
  }

}
