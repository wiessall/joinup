<?php

declare(strict_types = 1);

namespace Drupal\Tests\eu_oss_catalogue\Traits;

/**
 * EU OSS Catalogue trait.
 */
trait EuOssCatalogueTrait {

  /**
   * Creates testing terms.
   */
  protected function createTestingTerms(array $data): void {
    $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    foreach ($data as $vid => $terms) {
      foreach ($terms as $term) {
        $storage->create(['vid' => $vid, 'name' => $term])->save();
      }
    }
  }

}
