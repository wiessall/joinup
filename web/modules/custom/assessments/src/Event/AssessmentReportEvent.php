<?php

declare(strict_types=1);

namespace Drupal\assessments\Event;

use Drupal\assessments\AssessmentInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event class for assessment report notifications.
 */
final class AssessmentReportEvent extends Event {

  /**
   * Constructs an AssessmentReportEvent object.
   *
   * @param \Drupal\assessments\AssessmentInterface $entity
   *   The assessment report entity.
   * @param string|null $reason
   *   Optional reason for requiring updates.
   */
  public function __construct(
    protected readonly AssessmentInterface $entity,
    protected readonly ?string $reason = NULL,
  ) {}

  /**
   * Gets the assessment report entity.
   *
   * @return \Drupal\assessments\AssessmentInterface
   *   The assessment report entity.
   */
  public function getEntity(): AssessmentInterface {
    return $this->entity;
  }

  /**
   * Gets the reason for requiring updates.
   *
   * @return string|null
   *   The reason text, or NULL if not applicable.
   */
  public function getReason(): ?string {
    return $this->reason;
  }

}
