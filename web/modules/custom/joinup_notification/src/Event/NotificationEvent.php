<?php

declare(strict_types=1);

namespace Drupal\joinup_notification\Event;

use Drupal\Core\Entity\EntityInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * A notification event class.
 */
class NotificationEvent extends Event {

  /**
   * If the event succeeded.
   *
   * @var bool
   */
  protected $success = TRUE;

  public function __construct(
    protected string $operation,
    protected EntityInterface $entity,
  ) {}

  /**
   * Returns the operation string.
   *
   * @return string
   *   The operation string.
   */
  public function getOperation(): string {
    return $this->operation;
  }

  /**
   * Sets the operation string.
   *
   * @param string $operation
   *   The operation string.
   *
   * @return $this
   */
  public function setOperation($operation): self {
    $this->operation = $operation;
    return $this;
  }

  /**
   * Returns the entity object.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity object.
   */
  public function getEntity(): EntityInterface {
    return $this->entity;
  }

  /**
   * Sets the entity object.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity object.
   *
   * @return $this
   */
  public function setEntity(EntityInterface $entity): self {
    $this->entity = $entity;
    return $this;
  }

  /**
   * Sets the success flag.
   *
   * @param bool $success
   *   If the event succeeded.
   *
   * @return $this
   */
  public function setSuccess(bool $success): self {
    $this->success = $success;
    return $this;
  }

  /**
   * Tells if the operation succeeded.
   *
   * @return bool
   *   If the event succeeded.
   */
  public function isSuccessful(): bool {
    return $this->success;
  }

}
