<?php

/**
 * @file
 * Deploy hooks for Joinup RDF module.
 */

declare(strict_types=1);

use Drupal\Core\Language\LanguageInterface;

/**
 * Add URL alias for a taxonomy term.
 */
function joinup_rdf_deploy_111326(): void {
  $system_path = '/taxonomy/term/11197';
  $alias_path = '/taxonomy/term/http_e_f_fjoinup_ceu_fontology_ftopic_hstatistics_band_binformatino';

  $path_alias_repository = \Drupal::service('path_alias.repository');

  $existing_alias = $path_alias_repository->lookupByAlias($alias_path, LanguageInterface::LANGCODE_NOT_SPECIFIED);
  if (!$existing_alias) {
    $path_alias = \Drupal::entityTypeManager()->getStorage('path_alias')->create([
      'path' => $system_path,
      'alias' => $alias_path,
      'langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
    ]);

    $path_alias->save();
  }
}
