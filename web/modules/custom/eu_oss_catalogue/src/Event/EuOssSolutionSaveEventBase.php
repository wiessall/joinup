<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Event;

use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;
use Drupal\eu_oss_catalogue\Model\Project;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Base class for save event.
 */
abstract class EuOssSolutionSaveEventBase extends Event {

  /**
   * Constructs a new event instance.
   *
   * @param \Drupal\eu_oss_catalogue\Entity\OssSolutionInterface $solution
   *   The OSS Solution node entity.
   * @param \Drupal\eu_oss_catalogue\Model\Project $project
   *   The project value object.
   */
  public function __construct(
    protected OssSolutionInterface $solution,
    public readonly Project $project,
  ) {}

  /**
   * Returns the OSS Solution node entity.
   *
   * @return \Drupal\eu_oss_catalogue\Entity\OssSolutionInterface
   *   The OSS Solution node entity.
   */
  public function getSolution(): OssSolutionInterface {
    return $this->solution;
  }

}
