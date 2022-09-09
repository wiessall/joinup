<?php

declare(strict_types = 1);

namespace Drupal\joinup_group\Entity;

use Drupal\message\Entity\Message;
use Drupal\state_machine\Plugin\Field\FieldType\StateItemInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowState;

/**
 * Bundle class for announcement messages.
 */
class Announcement extends Message implements AnnouncementInterface {

  use GroupContentTrait;

  /**
   * {@inheritdoc}
   */
  public function getSubject(): ?string {
    return $this->get('subject')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getBody(): ?string {
    return $this->get('body')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getState(): WorkflowState {
    return $this->getStateFieldItem()->getWorkflow()->getState($this->getStateId());
  }

  /**
   * {@inheritdoc}
   */
  public function getStateFieldItem(): StateItemInterface {
    /** @var \Drupal\state_machine\Plugin\Field\FieldType\StateItemInterface $state_item */
    $state_item = $this->get('state')->first();
    return $state_item;
  }

  /**
   * {@inheritdoc}
   */
  public function getStateId(): string {
    return $this->getStateFieldItem()->getValue()['value'];
  }

  /**
   * {@inheritdoc}
   */
  public function getTransitions(): array {
    return $this->getStateFieldItem()->getTransitions();
  }

  /**
   * Returns the group reference field name.
   *
   * @return string
   *   The group reference field name.
   */
  protected function getGroupFieldName(): string {
    return 'group_reference';
  }

}
