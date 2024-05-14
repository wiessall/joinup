<?php

declare(strict_types=1);

namespace Drupal\eif\Entity;

use Drupal\joinup_rdf\Entity\RdfSyncEntityInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Interface for EI recommendation terms.
 */
interface EifRecommendationInterface extends RdfSyncEntityInterface, TermInterface {

  /**
   * Returns an enriched label.
   *
   * @return string
   *   A composite label.
   */
  public function getFullLabel(): string;

}
