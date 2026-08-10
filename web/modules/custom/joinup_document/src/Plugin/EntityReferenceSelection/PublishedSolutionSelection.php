<?php

declare(strict_types=1);

namespace Drupal\joinup_document\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\Attribute\EntityReferenceSelection;
use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides selection of published solutions, tolerating archived references.
 */
#[EntityReferenceSelection(
  id: "joinup_published_solution",
  label: new TranslatableMarkup("Published solution selection"),
  group: 'joinup_published_solution',
  weight: 0,
  entity_types: ["node"]
)]
class PublishedSolutionSelection extends DefaultSelection {

  /**
   * Whether archived solutions should be treated as referenceable.
   */
  protected bool $allowArchived = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS'): QueryInterface {
    $query = parent::buildEntityQuery($match, $match_operator);
    $query->condition('type', 'solution');
    if ($this->allowArchived) {
      $query->condition('status', 1);
    }
    else {
      $query->condition('field_state', 'published');
    }
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function validateReferenceableEntities(array $ids): array {
    $this->allowArchived = TRUE;
    try {
      return parent::validateReferenceableEntities($ids);
    }
    finally {
      $this->allowArchived = FALSE;
    }
  }

}
