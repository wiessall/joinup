<?php

declare(strict_types = 1);

namespace Drupal\joinup_group\Entity;

use Drupal\message\MessageInterface;
use Drupal\state_machine\Plugin\Field\FieldType\StateItemInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowState;

/**
 * Interface for announcement messages bundle class.
 */
interface AnnouncementInterface extends MessageInterface, GroupContentInterface {

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

  /**
   * Returns the announcement state instance.
   *
   * @return \Drupal\state_machine\Plugin\Workflow\WorkflowState
   *   The announcement state instance.
   */
  public function getState(): WorkflowState;

  /**
   * Returns the announcement state field item.
   *
   * @return \Drupal\state_machine\Plugin\Field\FieldType\StateItemInterface
   *   The announcement state field item.
   */
  public function getStateFieldItem(): StateItemInterface;

  /**
   * Returns the announcement state.
   *
   * @return string
   *   The announcement state.
   */
  public function getStateId(): string;

  /**
   * Gets the allowed transitions for the current state.
   *
   * @return \Drupal\state_machine\Plugin\Workflow\WorkflowTransition[]
   *   The allowed transitions, keyed by transition ID.
   */
  public function getTransitions(): array;

}
