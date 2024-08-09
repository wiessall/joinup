<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\Attribute\EntityReferenceSelection;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\taxonomy\Plugin\EntityReferenceSelection\TermSelection;
use Drupal\taxonomy\TermStorageInterface;

/**
 * Provides a taxonomy term selection where terms are grouped by their parent.
 *
 * This is inspired from https://www.drupal.org/project/taxonomy_container but
 * is adapted to use string ID for the parent term.
 */
#[EntityReferenceSelection(
  id: "grouped_taxonomy_term_selection",
  label: new TranslatableMarkup("Taxonomy term selection grouped by parent term"),
  group: "grouped_taxonomy_term_selection",
  weight: 0,
  entity_types: ["taxonomy_term"],
)]
class GroupedTaxonomyTermSelection extends TermSelection {

  /**
   * {@inheritdoc}
   */
  public function getReferenceableEntities($match = NULL, $match_operator = 'CONTAINS', $limit = 0): array {
    if ($match || $limit) {
      return parent::getReferenceableEntities($match, $match_operator, $limit);
    }

    $options = [];
    $bundles = $this->entityTypeBundleInfo->getBundleInfo('taxonomy_term');
    $targetBundles = $this->configuration['target_bundles'] ?? array_keys($bundles);
    $termStorage = $this->entityTypeManager->getStorage('taxonomy_term');
    assert($termStorage instanceof TermStorageInterface);

    foreach ($targetBundles as $bundle) {
      $terms = $termStorage->loadTree($bundle, 0, NULL, TRUE);
      if (!$terms) {
        continue;
      }

      $parentLabel = NULL;
      foreach ($terms as $term) {
        if (empty($term->parents[0])) {
          $parentLabel = $term->label();
          continue;
        }

        $options[$bundle][$parentLabel][$term->id()] = str_repeat('-', $term->depth) . $term->label();
      }
    }

    return $options;
  }

}
