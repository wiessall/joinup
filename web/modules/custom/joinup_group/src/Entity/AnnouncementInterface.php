<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Entity;

use Drupal\joinup_workflow\EntityWorkflowStateInterface;
use Drupal\message\MessageInterface;

/**
 * Interface for announcement messages bundle class.
 */
interface AnnouncementInterface extends MessageInterface, GroupContentInterface, EntityWorkflowStateInterface {

  /**
   * Sets the announcement subject.
   *
   * @param string $subject
   *   The announcement subject.
   *
   * @return $this
   */
  public function setSubject(string $subject): self;

  /**
   * Returns the announcement subject.
   *
   * @return string|null
   *   The announcement subject.
   */
  public function getSubject(): ?string;

  /**
   * Sets the announcement body.
   *
   * @param string $body
   *   The announcement body.
   *
   * @return $this
   */
  public function setBody(string $body): self;

  /**
   * Returns the announcement body.
   *
   * @return string|null
   *   The announcement body.
   */
  public function getBody(): ?string;

  /**
   * Returns the body format.
   *
   * @return string
   *   The body format.
   */
  public function getBodyFormat(): string;

}
