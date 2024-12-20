<?php

declare(strict_types=1);

namespace Drupal\joinup_discussion\Event;

use Drupal\joinup_discussion\Entity\DiscussionInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * A base class for discussion events.
 */
abstract class DiscussionEventBase extends Event {

  public function __construct(
    protected readonly DiscussionInterface $node,
  ) {}

  /**
   * Returns the discussion node.
   *
   * @return \Drupal\joinup_discussion\Entity\DiscussionInterface
   *   The discussion node.
   */
  public function getNode(): DiscussionInterface {
    return $this->node;
  }

}
