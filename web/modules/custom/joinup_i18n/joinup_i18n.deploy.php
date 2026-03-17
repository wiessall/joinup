<?php

/**
 * @file
 * Deploy hooks for Joinup i18n module.
 */

declare(strict_types=1);

use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\tmgmt\JobInterface;
use Drupal\tmgmt_ec_etranslation\Plugin\tmgmt\Translator\ETranslationTranslator;

/**
 * Remove leftovers.
 */
function joinup_i18n_deploy_201100(): void {
  // An erroneous translation has been created at an early stage for this link.
  if ($link = MenuLinkContent::load(20694)) {
    if ($link->hasTranslation('bg')) {
      $link->removeTranslation('bg');
      $link->save();
    }
  }

  // Duplicates of sources strings were detected. The root cause couldn't be
  // detected immediately, but the problem is deferred to ISAICP-10740.
  // @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-10740
  $db = \Drupal::database();
  $sql = <<<SQL
    SELECT GROUP_CONCAT(lid ORDER BY lid)
    FROM locales_source
    GROUP BY source, context
    HAVING COUNT(source) > 1
    SQL;

  $delete = [];
  foreach ($db->query($sql)->fetchCol() as $grouped) {
    $lids = explode(',', $grouped);
    array_shift($lids);
    $delete = [...$delete, ...$lids];
  }
  if ($delete) {
    $db->delete('locales_source')->condition('lid', $delete, 'IN')->execute();
    $db->delete('locales_location')->condition('sid', $delete, 'IN')->execute();
    $db->delete('locales_target')->condition('lid', $delete, 'IN')->execute();
    $db->delete('babel_source_instance')
      ->condition('plugin', 'locale')
      ->condition('id', $delete, 'IN')
      ->execute();
  }
}

/**
 * Creates continuous tasks for Babel strings.
 */
function joinup_i18n_deploy_201101(): void {
  foreach (\Drupal::languageManager()->getLanguages() as $langcode => $language) {
    if ($langcode !== 'en') {
      tmgmt_job_create('en', $langcode, 0, [
        'job_type' => JobInterface::TYPE_CONTINUOUS,
        'label' => "Babel: en > {$language->getId()}",
        'translator' => ETranslationTranslator::ID,
        'status' => JobInterface::STATE_CONTINUOUS,
      ])->save();
    }
  }
}
