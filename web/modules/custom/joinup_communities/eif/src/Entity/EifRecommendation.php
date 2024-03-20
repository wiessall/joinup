<?php

declare(strict_types=1);

namespace Drupal\eif\Entity;

use Drupal\joinup_taxonomy\Entity\MappedTerm;

/**
 * Bundle class for EIF recommendation terms.
 */
class EifRecommendation extends MappedTerm implements EifRecommendationInterface {

  /**
   * {@inheritdoc}
   */
  public function getFullLabel(): string {
    return $this->label() . ' - ' . $this->get('field_eif_related_term')->entity->label();
  }

}
