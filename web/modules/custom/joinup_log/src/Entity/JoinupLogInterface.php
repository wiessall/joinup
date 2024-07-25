<?php

declare(strict_types=1);

namespace Drupal\joinup_log\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\UserInterface;

/**
 * Provides an interface defining a joinup log entity type.
 */
interface JoinupLogInterface extends ContentEntityInterface, EntityOwnerInterface {

  /**
   * Returns the group entity, if any.
   *
   * @return \Drupal\joinup_group\Entity\GroupInterface|null
   *   The entity this log entry relates to, if any.
   */
  public function getGroup(): ?GroupInterface;

  /**
   * Returns the hostname of the user who triggered the event.
   *
   * @return string|null
   *   The hostname of the user who triggered the event.
   */
  public function getHostname(): ?string;

  /**
   * Returns the URL of the origin of the event.
   *
   * @return string|null
   *   The origin of the event.
   */
  public function getLocation(): ?string;

  /**
   * Returns the URL of referring page.
   *
   * @return string|null
   *   The referer of the event.
   */
  public function getReferer(): ?string;

  /**
   * Returns the severity level of the event.
   *
   * @see \Drupal\Core\Logger\RfcLogLevel::getLevels()
   *
   * @return int
   *   The severity of the event.
   */
  public function getSeverity(): int;

  /**
   * Returns the Unix timestamp of when event occurred.
   *
   * @return int
   *   The unix timestamp.
   */
  public function getTimestamp(): int;

  /**
   * Returns the type of log message, for example "user" or "page not found".
   *
   * @return string
   *   The type of the event.
   */
  public function getType(): string;

  /**
   * Returns the entity actor's entity.
   *
   * @return \Drupal\user\UserInterface|null
   *   The actor user entity.
   */
  public function getActor(): ?UserInterface;

  /**
   * Returns the entity actor's user ID.
   *
   * @return int|null
   *   The actor user ID.
   */
  public function getActorId(): ?int;

}
