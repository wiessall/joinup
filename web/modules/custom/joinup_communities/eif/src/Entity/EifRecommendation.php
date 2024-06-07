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
    $parts = explode(': ', $this->get('field_eif_related_term')->entity->label(), 2);
    $suffix = $parts[1] ?? $parts[0];
    return "{$this->label()} | $suffix";
  }

}
