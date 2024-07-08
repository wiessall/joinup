<?php

declare(strict_types=1);

namespace Drupal\joinup_log\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\rdf_entity\RdfInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a joinup log entity type.
 */
interface JoinupLogInterface extends ContentEntityInterface, EntityOwnerInterface {

  /**
   * Getter for the group entity.
   *
   * @return \Drupal\rdf_entity\RdfInterface|null
   *   RDF entity this log entry relates to, if any.
   */
  public function getGroupEntity(): ?RdfInterface;

  /**
   * Hostname of the user who triggered the event.
   *
   * @return string|null
   *   The hostname of the user who triggered the event.
   */
  public function getHostname(): ?string;

  /**
   * URL of the origin of the event.
   *
   * @return string|null
   *   The origin of the event.
   */
  public function getLocation(): ?string;

  /**
   * URL of referring page.
   *
   * @return string|null
   *   The referer of the event.
   */
  public function getReferer(): ?string;

  /**
   * The severity level of the event.
   *
   * @see \Drupal\Core\Logger\RfcLogLevel::getLevels()
   *
   * @return int
   *   The severity of the event.
   */
  public function getSeverity(): int;

  /**
   * Unix timestamp of when event occurred.
   *
   * @return int
   *   The unix timestamp.
   */
  public function getTimestamp(): int;

  /**
   * Type of log message, for example "user" or "page not found".
   *
   * @return string
   *   The type of the event.
   */
  public function getType(): string;

  /**
   * The id of the user who triggered the event.
   *
   * @return int
   *   The user id.
   */
  public function getUid(): int;

}
