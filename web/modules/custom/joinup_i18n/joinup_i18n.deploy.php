<?php

/**
 * @file
 * Deploy hooks for Joinup i18n module.
 */

declare(strict_types=1);

use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Update locale translation formulae for all active languages.
 */
function joinup_i18n_update_200500(): void {
  $languages = \Drupal::languageManager()->getLanguages();
  $pluralFormula = \Drupal::service('l10n_pconfig.drupalorg_plural_formula');
  foreach ($languages as $language) {
    $configurableLanguage = ConfigurableLanguage::load($language->getId());
    $pluralFormula->updatePluralFormula($configurableLanguage);
  }
}
