<?php

declare(strict_types = 1);

namespace Drupal\joinup_group\Entity;

use Drupal\joinup_workflow\EntityWorkflowStateInterface;
use Drupal\message\MessageInterface;

/**
 * Interface for announcement messages bundle class.
 */
interface AnnouncementInterface extends MessageInterface, GroupContentInterface, EntityWorkflowStateInterface {

  /**
   * Returns the announcement subject.
   *
   * @return string|null
   *   The announcement subject.
   */
  public function getSubject(): ?string;

  /**
   * Returns the announcement body.
   *
   * @return string|null
   *   The announcement body.
   */
  public function getBody(): ?string;

}
