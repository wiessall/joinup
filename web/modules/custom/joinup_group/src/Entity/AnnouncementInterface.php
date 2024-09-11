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

  /**
   * Returns a list of emails of members subscribed to receive announcements.
   *
   * Note that this method is not using the entity API as some groups may have
   * tens of thousands of members, and we need a fast way to queue them. Doing a
   * direct database query is faster.
   *
   * Also, passing an offset and a limit is mandatory in order to avoid huge
   * return arrays. It's the caller responsibility to pass the correct range and
   * reassemble the results according to its functionality.
   *
   * @param int $offset
   *   The query offset.
   * @param int $limit
   *   The query limit.
   *
   * @return string[]
   *   A list of groups members emails.
   */
  public function getRecipients(int $offset, int $limit): array;

  /**
   * Returns the number of active members subscribed to receive announcements.
   *
   * @return int
   *   The number of active members subscribed to receive announcements.
   */
  public function getRecipientsCount(): int;

  /**
   * Sets the professional domains reference value.
   *
   * @param array $ids
   *   The entity IDs of the topic terms.
   *
   * @return $this
   */
  public function setProfessionalDomains(array $ids): self;

  /**
   * Returns the professional domains reference value.
   *
   * @return array
   *   The IDs of the professional domain terms.
   */
  public function getProfessionalDomainIds(): array;

}
