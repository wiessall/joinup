<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Event;

use Drupal\eu_oss_catalogue\Model\Project;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event triggered just after a solution has been fetched from the provider.
 */
class EuOssPostFetchEvent extends Event {

  /**
   * Whether this OSS Solution should be cancelled by a listener.
   */
  protected bool $cancel = FALSE;

  /**
   * The cancellation reason.
   */
  protected ?string $cancellationReason;

  /**
   * Constructs a new event instance.
   *
   * @param \Drupal\eu_oss_catalogue\Model\Project $project
   *   The project value object.
   */
  public function __construct(protected Project $project) {}

  /**
   * Returns the fetched solution.
   *
   * @return \Drupal\eu_oss_catalogue\Model\Project
   *   The project value object
   */
  public function getProject(): Project {
    return $this->project;
  }

  /**
   * Cancels the import of this solution.
   *
   * @param string $reason
   *   The cancellation reason.
   *
   * @return $this
   */
  public function cancel(string $reason): self {
    $this->cancel = TRUE;
    $this->cancellationReason = $reason;
    $this->stopPropagation();
    return $this;
  }

  /**
   * Checks whether this import has been cancelled.
   *
   * @return bool
   *   Whether this import has been cancelled.
   */
  public function isCancelled(): bool {
    return $this->cancel;
  }

  /**
   * Returns the cancellation reason.
   *
   * @return string|null
   *   The cancellation reason or NULL if the federation is not cancelled.
   */
  public function getCancellationReason(): ?string {
    return $this->cancellationReason;
  }

}
