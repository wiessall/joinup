<?php

declare(strict_types=1);

namespace Drupal\joinup_taxonomy\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\Attribute\EntityReferenceSelection;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\taxonomy\Plugin\EntityReferenceSelection\TermSelection;
use Drupal\taxonomy\TermInterface;

/**
 * Tags term selection handler that allows referencing unpublished terms.
 */
#[EntityReferenceSelection(
  id: 'joinup_taxonomy',
  label: new TranslatableMarkup('Tags term selection (allows unpublished)'),
  group: 'joinup_taxonomy',
  weight: 1,
  entity_types: ['taxonomy_term'],
)]
class TagsViewLabelTermSelection extends TermSelection {

  /**
   * {@inheritdoc}
   */
  public function validateReferenceableEntities(array $ids): array {
    $valid = parent::validateReferenceableEntities($ids);
    $filtered = array_diff($ids, $valid);
    if (!$filtered) {
      return $valid;
    }
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    foreach ($filtered as $id) {
      $term = $storage->load($id);
      if ($term instanceof TermInterface && $term->bundle() === 'tags' && $term->access('view label')) {
        $valid[] = $id;
      }
    }
    return $valid;
  }

}
