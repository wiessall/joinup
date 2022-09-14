<?php

declare(strict_types = 1);

namespace Drupal\joinup_group\Entity;

use Drupal\joinup_workflow\EntityWorkflowStateTrait;
use Drupal\message\Entity\Message;

/**
 * Bundle class for announcement messages.
 */
class Announcement extends Message implements AnnouncementInterface {

  use EntityWorkflowStateTrait;
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
  public function getWorkflowStateFieldName(): string {
    return 'state';
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
