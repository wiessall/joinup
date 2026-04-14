<?php

/**
 * @file
 * Deploy hooks for Joinup i18n module.
 */

declare(strict_types=1);

use Drupal\babel\BabelStorageInterface;
use Drupal\babel\Model\Source;

/**
 * Locale source strings with duplicates.
 */
function joinup_i18n_deploy_201200(): void {
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
 * Limit language translation to EU languages.
 */
function joinup_i18n_deploy_201201(array &$sandbox): string {
  if (empty($sandbox['hashes'])) {
    $euLanguages = [
      'Bulgarian',
      'Czech',
      'Danish',
      'German',
      'Greek',
      'English',
      'Spanish',
      'Estonian',
      'Finnish',
      'French',
      'Irish',
      'Croatian',
      'Hungarian',
      'Italian',
      'Lithuanian',
      'Latvian',
      'Maltese',
      'Dutch',
      'Polish',
      'Portuguese',
      'Romanian',
      'Slovak',
      'Slovenian',
      'Swedish',
    ];

    $names = \Drupal::entityQueryAggregate('taxonomy_term')->accessCheck(FALSE)
      ->condition('vid', 'language')
      ->condition('name', $euLanguages, 'NOT IN')
      ->groupBy('langcode')
      ->groupBy('name')
      ->execute();
    if (!$names) {
      $sandbox['#finished'] = 1;
      return 'No language terms to process';
    }

    // Get hashes out of names.
    $sandbox['hashes'] = array_map(
      fn(string $name): string => (new Source($name, ''))->getHash(),
      array_column($names, 'name'),
    );
    $sandbox['total'] = count($sandbox['hashes']);
    $sandbox['progress'] = 0;
  }

  $hashes = array_splice($sandbox['hashes'], 0, 10);
  $storage = \Drupal::service(BabelStorageInterface::class);
  foreach ($hashes as $hash) {
    $instances = $storage->getSourceStringInstances($hash);
    if (!isset($instances['content_entity:taxonomy_term'])) {
      // This could not happen, but just in case.
      continue;
    }

    if (count($instances) > 1) {
      // This source string is handled by more than one plugin.
      continue;
    }

    $ids = reset($instances);
    if (count($ids) > 1) {
      // Other vocabularies are using this source string.
      continue;
    }

    $storage->updateStatusForHash($hash, FALSE);
  }
  $sandbox['progress'] += count($hashes);

  $sandbox['#finished'] = (int) empty($sandbox['hashes']);

  return "Processed {$sandbox['progress']} out of {$sandbox['total']} hashes";
}
