<?php

/**
 * @file
 * Deploy hooks for Joinup i18n module.
 */

declare(strict_types=1);

use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;
use Drupal\joinup_i18n\Hook\JoinupI18nHooks;
use Drupal\tmgmt\JobInterface;
use Drupal\tmgmt_ec_etranslation\Plugin\tmgmt\Translator\ETranslationTranslator;

/**
 * Queue path alias creation for all enabled languages on existing nodes.
 */
function joinup_i18n_deploy_201300(array &$sandbox): string {
  if (!isset($sandbox['nids'])) {
    $sandbox['nids'] = array_values(\Drupal::entityTypeManager()
      ->getStorage('node')->getQuery()
      ->accessCheck(FALSE)->execute());
    $sandbox['total'] = count($sandbox['nids']);
    $sandbox['processed'] = 0;
  }

  if (empty($sandbox['nids'])) {
    $sandbox['#finished'] = 1;
    return "No nodes to process.";
  }

  $batch_size = 500;
  $nids = array_splice($sandbox['nids'], 0, $batch_size);
  $queue = \Drupal::queue('joinup_i18n:translation_alias');

  foreach ($nids as $nid) {
    $queue->createItem([
      'entity_type_id' => 'node',
      'entity_id' => $nid,
    ]);
  }

  $sandbox['processed'] += count($nids);
  $sandbox['#finished'] = $sandbox['total'] ? $sandbox['processed'] / $sandbox['total'] : 1;

  return "Queued {$sandbox['processed']}/{$sandbox['total']} nodes for alias generation.";
}

/**
 * Create continuous jobs for all enabled languages.
 */
function joinup_i18n_deploy_201301(array &$sandbox): string {
  if (!isset($sandbox['pairs'])) {
    $langcodes = array_keys(\Drupal::languageManager()->getLanguages());

    // Get permutations of language codes.
    for ($i = 0; $i < count($langcodes); $i++) {
      if ($langcodes[$i] === 'en') {
        // Continuous jobs with English as source language already exist.
        continue;
      }

      for ($j = 0; $j < count($langcodes); $j++) {
        if ($i !== $j) {
          $sandbox['pairs'][] = [$langcodes[$i], $langcodes[$j]];
        }
      }
    }

    $sandbox['continuous_settings'] = \Drupal::service(JoinupI18nHooks::class)->getContinuousSettings();

    // Rename legacy Babel continuous jobs and align their continuous_settings.
    $job_storage = \Drupal::entityTypeManager()->getStorage('tmgmt_job');
    $ids = $job_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('job_type', JobInterface::TYPE_CONTINUOUS)
      ->condition('label', 'Babel:%', 'LIKE')
      ->condition('source_language', 'en')
      ->execute();

    foreach ($job_storage->loadMultiple($ids) as $job) {
      $job->set('label', "Joinup: en > {$job->getTargetLangcode()}")
        ->set('continuous_settings', $sandbox['continuous_settings'])
        ->save();
    }
  }

  [$source, $target] = array_shift($sandbox['pairs']);
  tmgmt_job_create($source, $target, values: [
    'label' => "Joinup: $source > $target",
    'translator' => ETranslationTranslator::ID,
    'state' => JobInterface::STATE_CONTINUOUS,
    'job_type' => JobInterface::TYPE_CONTINUOUS,
    'continuous_settings' => $sandbox['continuous_settings'],
  ])->save();

  $sandbox['#finished'] = (int) empty($sandbox['pairs']);

  return "Created continuous job for $source > $target";
}

/**
 * Set OSS solution langcodes to their relevant language.
 */
function joinup_i18n_deploy_201302(array &$sandbox): string {
  $storage = \Drupal::entityTypeManager()->getStorage('node');
  $db = \Drupal::database();

  if (!isset($sandbox['nids'])) {
    $sandbox['nids'] = array_values($storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'oss_solution')
      ->execute());
    $sandbox['total'] = count($sandbox['nids']);
    $sandbox['progress'] = 0;

    // Remove legacy TMGMT jobs, job items and messages.
    $tjids = $db->query("SELECT tjid FROM {tmgmt_job} WHERE translator = :translator AND job_type = :type", [
      ':translator' => ETranslationTranslator::ID,
      ':type' => JobInterface::TYPE_NORMAL,
    ])->fetchCol();
    if ($tjids) {
      $args = [':ids[]' => $tjids];
      $db->query("DELETE FROM {tmgmt_message} WHERE tjid IN (:ids[])", $args);
      $db->query("DELETE FROM {tmgmt_job_item} WHERE tjid IN (:ids[])", $args);
      $db->query("DELETE FROM {tmgmt_job} WHERE tjid IN (:ids[])", $args);
    }

    if (empty($sandbox['nids'])) {
      $sandbox['#finished'] = 1;
      return 'No OSS solutions to update.';
    }
  }

  $nids = array_splice($sandbox['nids'], 0, 20);
  $sandbox['progress'] += count($nids);

  foreach ($storage->loadMultiple($nids) as $nid => $ossSolution) {
    assert($ossSolution instanceof OssSolutionInterface);

    $relevantLang = $ossSolution->getRelevantLangCode();
    $defaultLang = $ossSolution->language()->getId();
    if ($relevantLang === $defaultLang) {
      continue;
    }

    // Perform direct queries as changing the default language is not supported.
    // @see \Drupal\Core\Entity\ContentEntityBase::onChange
    // @see https://www.drupal.org/node/2443989
    foreach (['node_field_data', 'node_field_revision'] as $table) {
      $db->query("UPDATE {{$table}} SET default_langcode = 0 WHERE nid = :nid AND langcode = :langcode", [
        ':nid' => $nid,
        ':langcode' => $defaultLang,
      ]);
      $db->query("UPDATE {{$table}} SET default_langcode = 1 WHERE nid = :nid AND langcode = :langcode", [
        ':nid' => $nid,
        ':langcode' => $relevantLang,
      ]);
    }
    foreach (['node', 'node_revision'] as $table) {
      $db->query("UPDATE {{$table}} SET langcode = :langcode WHERE nid = :nid", [
        ':langcode' => $relevantLang,
        ':nid' => $nid,
      ]);
    }

    tmgmt_content_create_continuous_job_items($ossSolution);
  }

  $sandbox['#finished'] = empty($sandbox['nids']) ? 1 : ($sandbox['progress'] / $sandbox['total']);

  return sprintf('Processed %.1f%% OSS solutions', $sandbox['#finished'] * 100);
}
