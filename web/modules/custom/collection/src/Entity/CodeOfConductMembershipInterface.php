<?php

declare(strict_types=1);

namespace Drupal\collection\Entity;

use Drupal\node\NodeInterface;

/**
 * Interface for memberships that store and retrieve Code of Conduct data.
 */
interface CodeOfConductMembershipInterface {

  /**
   * Checks if the latest revision of the Code of Conduct has been accepted.
   *
   * This verifies whether the user has explicitly accepted the most
   * recent revision of the Code of Conduct for a given collection enti
   *
   * @param \Drupal\collection\Entity\CollectionInterface $collection
   *   Collection entity.
   *
   * @return bool
   *  TRUE if the latest document revision is accepted, FALSE otherwise.
   */
  public function isLastDocumentRevisionAccepted(CollectionInterface $collection): bool;

  /**
   * Marks the given Code of Conduct document as accepted.
   *
   * This records the user's acceptance of a specific Code of Conduct
   * document, which can later be referenced to verify compliance.
   *
   * @param \Drupal\node\NodeInterface $codeOfConduct
   *   The Code of Conduct document node.
   *
   * @return self
   *   The current membership instance, for method chaining.
   */
  public function setAcceptedDocument(NodeInterface $codeOfConduct): self;

}
