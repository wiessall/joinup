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
   *   Indexed array of terms, each being an array of values passed to
   *   EntityStorageInterface::create.
   * @param array $context
   *   The batch operation context.
   */
  public static function createTerms(array $items, array &$context): void {
    $termStorage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $imported = 0;

    foreach ($items as $item) {
      $existingTerms = $termStorage->loadByProperties([
        'name' => $item['name'],
        'vid' => $item['vid'],
      ]);

      if (empty($existingTerms)) {
        $termStorage->create($item)->save();
        $imported++;
      }
    }
    $context['message'] = t('Imported @count terms in controlled vocabularies', [
      '@count' => $imported,
    ]);
  }

}
