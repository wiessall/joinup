<?php

/**
 * @file
 * Deploy hooks for the Joinup core module.
 */

declare(strict_types=1);

use Drupal\Core\Database\Query\Merge;
use EasyRdf\Graph;

/**
 * Refresh the spdx_licence vocabulary from resources/vocab/licenses.rdf.
 */
function joinup_core_deploy_201300(): string {
  $file = DRUPAL_ROOT . '/../resources/vocab/licenses.rdf';
  if (!is_readable($file)) {
    throw new \RuntimeException("SPDX RDF source not readable: $file");
  }

  $graph = new Graph();
  $graph->parseFile($file);

  $stats = \Drupal::service('joinup_taxonomy.vocabulary_updater')
    ->registerGraph('http://spdx_licence', $graph)
    ->registerVocabulary('spdx_licence', 'http://spdx.org/rdf/terms#License', 'http://spdx_licence')
    ->update();

  return sprintf(
    'spdx_licence refreshed: inserted=%d, updated=%d, deleted=%d.',
    $stats['spdx_licence']['inserted'], $stats['spdx_licence']['updated'], $stats['spdx_licence']['deleted'],
  );
}

/**
 * Fix 16 release nodes that have bare /release path aliases with no version.
 */
function joinup_core_deploy_201301(): string {
  $fixes = [
    721271 => [
      'alias' => '/collection/are3na/solution/re3gistry/release/1.3',
      'release_number' => '1.3',
      'new_title' => 'Re3gistry',
    ],
    721201 => [
      'alias' => '/collection/e-government-innovation-center-egiz/solution/moa-id/release/3.0.2',
      'release_number' => '3.0.2',
      'new_title' => 'MOA-ID',
    ],
    721300 => [
      'alias' => '/collection/e-government-innovation-center-egiz/solution/moa-spss/release/3.1.0',
      'release_number' => '3.1.0',
      'new_title' => 'MOA-SPSS',
    ],
    721191 => [
      'alias' => '/collection/e-government-innovation-center-egiz/solution/mocca/release/1.3.18',
      'release_number' => '1.3.18',
      'new_title' => 'MOCCA',
    ],
    721274 => [
      'alias' => '/collection/e-government-innovation-center-egiz/solution/pdf-over/release/4.1.11',
      'release_number' => '4.1.11',
      'new_title' => 'PDF-Over',
    ],
    720027 => [
      'alias' => '/collection/e-government-innovation-center-egiz/solution/pdf/release/4.1.0',
      'release_number' => '4.1.0',
      'new_title' => 'PDF-AS',
    ],
    720433 => [
      'alias' => '/collection/eidentity-and-esignature/solution/signature-verification-portal/release/1.8.1',
      'release_number' => '1.8.1',
      'new_title' => 'signature-verification',
    ],
    720436 => [
      'alias' => '/collection/eparticipation-and-evoting/solution/eci-online-collection-software-ocs/release/1.8',
      'release_number' => '1.8',
      'new_title' => 'OCS',
    ],
    721190 => [
      'alias' => '/collection/eparticipation-and-evoting/solution/eusurvey/release/1.2.2',
      'release_number' => '1.2.2',
      'new_title' => 'EUSurvey',
    ],
    // GITB TDL Controlled Vocabulary has no version; /no-version is taken by
    // node/721281, so /no-version-0 is used.
    721283 => [
      'alias' => '/collection/interoperability-test-bed-repository/solution/interoperability-test-bed/release/no-version-0',
      'release_number' => 'no-version',
      'new_title' => NULL,
    ],
    // "Visualization of traffic..." has no version; /no-version is taken by
    // node/720016, so /no-version-0 is used.
    720017 => [
      'alias' => '/collection/portal-support/solution/joinup-archive/release/no-version-0',
      'release_number' => 'no-version',
      'new_title' => NULL,
    ],
    // STORK 2.0: the "2.0" is not that much of a version rather than a title
    // itself.
    720421 => [
      'alias' => '/collection/secure-identity-across-borders-linked-stork/solution/stork/release/2.0',
      'release_number' => '2.0',
      'new_title' => NULL,
    ],
    720478 => [
      'alias' => '/collection/semic-support-centre/solution/asset-description-metadata-schema-adms/release/0.8',
      'release_number' => '0.8',
      'new_title' => 'ADMS',
    ],
    721244 => [
      'alias' => '/collection/semic-support-centre/solution/core-criterion-and-core-evidence-vocabulary/release/draft-3',
      'release_number' => 'Draft 3',
      'new_title' => 'Core Criterion and Core Evidence Vocabulary',
    ],
    721219 => [
      'alias' => '/collection/semic-support-centre/solution/core-public-organisation-vocabulary/release/draft-3',
      'release_number' => 'Draft 3',
      'new_title' => 'Core Public Organisation Vocabulary',
    ],
    721226 => [
      'alias' => '/collection/semic-support-centre/solution/dcat-application-profile-implementation-guidelines/release/no-version',
      'release_number' => 'no-version',
      'new_title' => NULL,
    ],
  ];

  $database = \Drupal::database();
  $aliases_updated = 0;
  $release_numbers_inserted = 0;
  $release_numbers_updated = 0;
  $titles_updated = 0;

  foreach ($fixes as $nid => $fix) {
    $path = '/node/' . $nid;

    // 1. Replace the path alias directly to prevent the redirect module from
    //    creating an unwanted redirect record from the old bare /release alias.
    $aliases_updated += (int) $database->update('path_alias')
      ->fields(['alias' => $fix['alias']])
      ->condition('path', $path)
      ->execute();

    // 2. Set the release_number field.
    $vid = $database->select('node_field_data', 'n')
      ->fields('n', ['vid'])
      ->condition('nid', $nid)
      ->execute()
      ->fetchField();

    $release_fields = [
      'bundle' => 'release',
      'revision_id' => $vid,
      'langcode' => 'en',
      'release_number_value' => $fix['release_number'],
    ];
    $status = $database->merge('node__release_number')
      ->keys(['entity_id' => $nid, 'deleted' => 0, 'delta' => 0])
      ->fields($release_fields)
      ->execute();
    if ($status === Merge::STATUS_INSERT) {
      $release_numbers_inserted++;
    }
    else {
      $release_numbers_updated++;
    }

    $database->merge('node_revision__release_number')
      ->keys([
        'entity_id' => $nid,
        'revision_id' => $vid,
        'deleted' => 0,
        'delta' => 0,
      ])
      ->fields(array_diff_key($release_fields, ['revision_id' => '']))
      ->execute();

    // 3. Strip the version from the title where applicable.
    if ($fix['new_title'] !== NULL) {
      $changed = (int) $database->update('node_field_data')
        ->fields(['title' => $fix['new_title']])
        ->condition('nid', $nid)
        ->execute();
      if ($changed > 0) {
        $titles_updated++;
      }
      $database->update('node_field_revision')
        ->fields(['title' => $fix['new_title']])
        ->condition('nid', $nid)
        ->execute();
    }
  }

  return sprintf(
    'Updated %d path aliases; set %d release numbers (%d inserted, %d updated); stripped version from %d titles.',
    $aliases_updated,
    $release_numbers_inserted + $release_numbers_updated,
    $release_numbers_inserted,
    $release_numbers_updated,
    $titles_updated,
  );
}

/**
 * Fix aliases mangled by pathauto and delete stale redirect records.
 */
function joinup_core_deploy_201302(): string {
  $database = \Drupal::database();

  // Reset aliases.
  $aliases = [
    '/node/721271' => '/collection/are3na/solution/re3gistry/release/1.3',
    '/node/721201' => '/collection/e-government-innovation-center-egiz/solution/moa-id/release/3.0.2',
    '/node/721300' => '/collection/e-government-innovation-center-egiz/solution/moa-spss/release/3.1.0',
    '/node/721191' => '/collection/e-government-innovation-center-egiz/solution/mocca/release/1.3.18',
    '/node/721274' => '/collection/e-government-innovation-center-egiz/solution/pdf-over/release/4.1.11',
    '/node/720027' => '/collection/e-government-innovation-center-egiz/solution/pdf/release/4.1.0',
    '/node/720433' => '/collection/eidentity-and-esignature/solution/signature-verification-portal/release/1.8.1',
    '/node/720436' => '/collection/eparticipation-and-evoting/solution/eci-online-collection-software-ocs/release/1.8',
    '/node/721190' => '/collection/eparticipation-and-evoting/solution/eusurvey/release/1.2.2',
    '/node/721283' => '/collection/interoperability-test-bed-repository/solution/interoperability-test-bed/release/no-version-0',
    '/node/720017' => '/collection/portal-support/solution/joinup-archive/release/no-version-0',
    '/node/720421' => '/collection/secure-identity-across-borders-linked-stork/solution/stork/release/2.0',
    '/node/720478' => '/collection/semic-support-centre/solution/asset-description-metadata-schema-adms/release/0.8',
    '/node/721244' => '/collection/semic-support-centre/solution/core-criterion-and-core-evidence-vocabulary/release/draft-3',
    '/node/721219' => '/collection/semic-support-centre/solution/core-public-organisation-vocabulary/release/draft-3',
    '/node/721226' => '/collection/semic-support-centre/solution/dcat-application-profile-implementation-guidelines/release/no-version',
  ];

  $aliases_reset = 0;
  foreach ($aliases as $path => $alias) {
    $aliases_reset += (int) $database->update('path_alias')
      ->fields(['alias' => $alias])
      ->condition('path', $path)
      ->execute();
  }

  // 2. Delete the redirect records that subpathauto was resolving instead of
  //    falling through to the solution alias. Keyed by source path so the
  //    deletion works across environments regardless of auto-increment IDs.
  $source_paths = [
    'collection/are3na/solution/re3gistry/release',
    'collection/e-government-innovation-center-egiz/solution/moa-id/release',
    'collection/e-government-innovation-center-egiz/solution/moa-spss/release',
    'collection/e-government-innovation-center-egiz/solution/mocca/release',
    'collection/e-government-innovation-center-egiz/solution/pdf-over/release',
    'collection/e-government-innovation-center-egiz/solution/pdf/release',
    'collection/eidentity-and-esignature/solution/signature-verification-portal/release',
    'collection/eparticipation-and-evoting/solution/eci-online-collection-software-ocs/release',
    'collection/eparticipation-and-evoting/solution/eusurvey/release',
    'collection/interoperability-test-bed-repository/solution/interoperability-test-bed/release',
    'collection/portal-support/solution/joinup-archive/release',
    'collection/secure-identity-across-borders-linked-stork/solution/stork/release',
    'collection/semic-support-centre/solution/asset-description-metadata-schema-adms/release',
    'collection/semic-support-centre/solution/core-criterion-and-core-evidence-vocabulary/release',
    'collection/semic-support-centre/solution/core-public-organisation-vocabulary/release',
    'collection/semic-support-centre/solution/dcat-application-profile-implementation-guidelines/release',
  ];

  $storage = \Drupal::entityTypeManager()->getStorage('redirect');
  $ids = $database->select('redirect', 'r')
    ->fields('r', ['rid'])
    ->condition('redirect_source__path', $source_paths, 'IN')
    ->execute()
    ->fetchCol();

  if ($ids) {
    $storage->delete($storage->loadMultiple($ids));
  }

  return sprintf(
    'Reset %d of %d path aliases; deleted %d stale redirect records.',
    $aliases_reset,
    count($aliases),
    count($ids),
  );
}

/**
 * Populates the new published_at field.
 */
function joinup_core_deploy_201303(array &$sandbox): string {
  $storage = \Drupal::entityTypeManager()->getStorage('assessment');

  if (!isset($sandbox['total'])) {
    /** @var \Drupal\Core\Extension\ExtensionPathResolver $path_resolver */
    $pathResolver = \Drupal::service('extension.path.resolver');
    $modulePath = $pathResolver->getPath('module', 'joinup_core');
    $filePath = DRUPAL_ROOT . '/' . $modulePath . '/fixtures/assessment_migration_2026-02-11_14-45-23.csv';

    $sandbox['csv'] = [];
    if (($handle = fopen($filePath, 'r')) !== FALSE) {
      while (($data = fgetcsv($handle, 0, ',')) !== FALSE) {
        // Report Name, Webform ID.
        $reportName = trim($data[5]);
        $sandbox['csv'][$reportName] = $data[0];
      }
      fclose($handle);
    }

    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'report')
      ->execute();
    $sandbox['total'] = count($ids);
    $sandbox['ids'] = array_values($ids);
    $sandbox['current'] = 0;
  }

  $items = array_slice($sandbox['ids'], $sandbox['current'], 50);

  foreach ($items as $id) {
    $sandbox['current']++;
    $entity = $storage->load($id);

    if (!$entity) {
      continue;
    }

    $reportName = $entity->get('name')->value;
    if (!empty($sandbox['csv'][$reportName])) {
      $webformId = $sandbox['csv'][$reportName];
      $nodeData = \Drupal::database()->select('node__submission', 'ns')
        ->fields('ns', ['entity_id', 'revision_id'])
        ->condition('ns.submission_target_id', $webformId)
        ->execute()
        ->fetchAssoc();

      if ($nodeData) {
        $publishedAt = \Drupal::database()->select('node_field_revision', 'nfr')
          ->fields('nfr', ['published_at'])
          ->condition('nfr.nid', $nodeData['entity_id'])
          ->condition('nfr.vid', $nodeData['revision_id'])
          // It needs to be published.
          ->condition('nfr.status', 1)
          ->execute()
          ->fetchField();

        if ($publishedAt) {
          $entity->set('published_at', $publishedAt);
          $entity->save();
          continue;
        }
      }
    }

    $changedTime = $entity->get('changed')->value;
    $entity->set('published_at', $changedTime);
    $entity->save();
  }

  $sandbox['#finished'] = ($sandbox['total'] == 0) ? 1 : ($sandbox['current'] / $sandbox['total']);

  return sprintf('Processed %d of %d assessments.', $sandbox['current'], $sandbox['total']);
}
