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
 * Remove leftover.
 */
function joinup_i18n_deploy_200800(): void {
  $link = MenuLinkContent::load(20694);
  $link->removeTranslation('bg');
  $link->save();
}

/**
 * Creates continuous tasks for Babel strings.
 */
function joinup_i18n_deploy_200801(): void {
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

/**
 * Schedule job items creation.
 */
function joinup_i18n_deploy_200802(array &$sandbox): string {
  $queue = \Drupal::queue('babel_tmgmt_continuous_job_item');
  $queue->createQueue();

  if (!isset($sandbox['hashes'])) {
    $sandbox['hashes'] = \Drupal::database()->select('babel_source')
      ->fields('babel_source', ['hash'])
      ->condition('status', 1)
      ->execute()
      ->fetchCol();
    $sandbox['progress'] = 0;
    $sandbox['total'] = count($sandbox['hashes']);
  }

  $hashes = array_splice($sandbox['hashes'], 0, 100);
  array_walk($hashes, fn(string $hash) => $queue->createItem(['hash' => $hash]));

  $sandbox['progress'] += count($hashes);
  $sandbox['#finished'] = (int) empty($sandbox['hashes']);

  return "Scheduled {$sandbox['progress']} out of {$sandbox['total']} source string hashes";
}
