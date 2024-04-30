<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Ajax\CommandInterface;
use Drupal\Core\Render\AttachmentsTrait;

/**
 * Allows subscribers to alter or replace an Ajax command.
 */
class AjaxCommandEvent extends Event {

  use AttachmentsTrait;

  /**
   * The Ajax command.
   *
   * @var \Drupal\Core\Ajax\CommandInterface
   */
  public CommandInterface $command;

  /**
   * Context data that might concern the subscribers.
   *
   * @var array
   *   Context data.
   */
  public array $context;

  /**
   * Constructs a new event instance.
   *
   * @param \Drupal\Core\Ajax\CommandInterface $command
   *   The Ajax command.
   * @param array $context
   *   Context data that might concern the subscribers.
   */
  public function __construct(CommandInterface $command, array $context = []) {
    $this->command = $command;
    $this->context = $context;
  }

  /**
   * Sets the Ajax command.
   *
   * @param \Drupal\Core\Ajax\CommandInterface $command
   *   The Ajax command.
   *
   * @return $this
   */
  public function setCommand(CommandInterface $command): self {
    $this->command = $command;
    return $this;
  }

  /**
   * Returns the Ajax command.
   *
   * @return \Drupal\Core\Ajax\CommandInterface
   *   The Ajax command.
   */
  public function getCommand(): CommandInterface {
    return $this->command;
  }

  /**
   * Returns the context.
   *
   * @return array
   *   Context data as array.
   */
  public function getContext(): array {
    return $this->context;
  }

}
