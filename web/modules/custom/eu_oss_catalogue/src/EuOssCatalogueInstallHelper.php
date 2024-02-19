<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue;

/**
 * Install helper.
 */
class EuOssCatalogueInstallHelper {

  /**
   * Creates terms in the controlled vocabularies when installing.
   *
   * @param array $items
   *   Indexed array of terms, each having the pattern "$vid:$name".
   * @param array $context
   *   The batch operation context.
   */
  public static function createTerms(array $items, array &$context): void {
    $termStorage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    foreach ($items as $item) {
      [$vid, $term] = explode(':', $item, 2);
      $termStorage->create(['vid' => $vid, 'name' => $term])->save();
    }
    $context['message'] = t('Imported @count terms in controlled vocabularies', [
      '@count' => count($items),
    ]);
  }

}
