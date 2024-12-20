<?php

declare(strict_types=1);

namespace Drupal\joinup_discussion\Event;

use Drupal\joinup_discussion\Entity\DiscussionInterface;

/**
 * An event to fire whenever a discussion is updated.
 */
class DiscussionUpdateEvent extends DiscussionEventBase {

  public function __construct(
    readonly DiscussionInterface $node,
    protected readonly array $changedFields,
  ) {
    parent::__construct($node);
  }

  /**
   * Returns the list of changed fields.
   *
   * @return array
   *   The list of changed fields, keyed by field name.
   */
  public function getChangedFields(): array {
    return $this->changedFields;
  }

}
