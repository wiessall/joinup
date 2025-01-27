<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Entity;

use Drupal\collection\Entity\CollectionInterface;
use Drupal\node\NodeInterface;

/**
 * Interface of memberships being able to store/retrieve Code of Conduct data.
 */
interface CodeOfConductMembershipInterface {

  /**
   * Checks if the last revision of Code of Conduct is accepted.
   *
   * @param \Drupal\collection\Entity\CollectionInterface $node
   *   Collection entity.
   *
   * @return bool
   */
  public function isLastRevisionAccepted(CollectionInterface $node): bool;

  /**
   * Sets the revision ID of the Code of Conduct.
   *
   * @param \Drupal\node\NodeInterface $codeOfConduct
   *   Code of Conduct.
   *
   * @return self
   *   The membership instance.
   */
  public function setAcceptedRevisionId(NodeInterface $codeOfConduct): self;

}
