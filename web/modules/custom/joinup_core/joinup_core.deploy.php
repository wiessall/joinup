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
    if (!empty(ASSESSMENT_NAME_WEBFORM_ID_MAPPING[$reportName])) {
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

const ASSESSMENT_NAME_WEBFORM_ID_MAPPING = [
  "Assessment #758" => 758,
  "Assessment #759" => 759,
  "Assessment #773" => 773,
  "Assessment #777" => 777,
  "Assessment #778" => 778,
  "Assessment #779" => 779,
  "Assessment #781" => 781,
  "Assessment #784" => 784,
  "Assessment #785" => 785,
  "Assessment #1190" => 1190,
  "Assessment #1201" => 1201,
  "Assessment #1234" => 1234,
  "Assessment #1272" => 1272,
  "Assessment #1592" => 1592,
  "Assessment #1620" => 1620,
  "Assessment #1629" => 1629,
  "Assessment #1647" => 1647,
  "Assessment #1653" => 1653,
  "Assessment #1655" => 1655,
  "Assessment #1717" => 1717,
  "Assessment #1728" => 1728,
  "Assessment #1729" => 1729,
  "Assessment #1897" => 1897,
  "Assessment #1899" => 1899,
  "Assessment #1912" => 1912,
  "Assessment #2042" => 2042,
  "Assessment #2127" => 2127,
  "Assessment #2163" => 2163,
  "Assessment #2176" => 2176,
  "Assessment #2200" => 2200,
  "Assessment #2307" => 2307,
  "Assessment #2311" => 2311,
  "Assessment #2323" => 2323,
  "Assessment #2328" => 2328,
  "Assessment #2441" => 2441,
  "Assessment #2559" => 2559,
  "Assessment #2578" => 2578,
  "Assessment #2598" => 2598,
  "Assessment #2622" => 2622,
  "Assessment #2658" => 2658,
  "Assessment #2683" => 2683,
  "Assessment #2716" => 2716,
  "Assessment #2728" => 2728,
  "Assessment #2730" => 2730,
  "Assessment #2731" => 2731,
  "Assessment #2733" => 2733,
  "Assessment #2734" => 2734,
  "Assessment #2736" => 2736,
  "Assessment #2737" => 2737,
  "Assessment #2738" => 2738,
  "Assessment #2739" => 2739,
  "Assessment #2740" => 2740,
  "Assessment #2741" => 2741,
  "Assessment #2742" => 2742,
  "Assessment #2743" => 2743,
  "Assessment #2744" => 2744,
  "Assessment #2746" => 2746,
  "Assessment #2747" => 2747,
  "Assessment #2748" => 2748,
  "Assessment #2749" => 2749,
  "Assessment #2750" => 2750,
  "Assessment #2751" => 2751,
  "Assessment #2752" => 2752,
  "Assessment #2753" => 2753,
  "Assessment #2754" => 2754,
  "Assessment #2755" => 2755,
  "Assessment #2756" => 2756,
  "Assessment #2757" => 2757,
  "Assessment #2760" => 2760,
  "Assessment #2772" => 2772,
  "Assessment #2793" => 2793,
  "Assessment #2848" => 2848,
  "Assessment #2849" => 2849,
  "Assessment #2850" => 2850,
  "Assessment #2856" => 2856,
  "Assessment #2869" => 2869,
  "Assessment #2870" => 2870,
  "Assessment #2871" => 2871,
  "Assessment #2872" => 2872,
  "Assessment #2873" => 2873,
  "Assessment #2874" => 2874,
  "Assessment #2875" => 2875,
  "Assessment #2974" => 2974,
  "Assessment #2976" => 2976,
  "Assessment #2977" => 2977,
  "Assessment #2978" => 2978,
  "Assessment #3030" => 3030,
  "Assessment #3032" => 3032,
  "Assessment #3050" => 3050,
  "Assessment #3061" => 3061,
  "Assessment #3062" => 3062,
  "Assessment #3063" => 3063,
  "Assessment #3064" => 3064,
  "Assessment #3072" => 3072,
  "Assessment #3073" => 3073,
  "Assessment #3158" => 3158,
  "Assessment #3159" => 3159,
  "Assessment #3160" => 3160,
  "Assessment #3161" => 3161,
  "Assessment #3162" => 3162,
  "Assessment #3479" => 3479,
  "Assessment #3486" => 3486,
  "Assessment #3550" => 3550,
  "Assessment #3592" => 3592,
  "Assessment #3613" => 3613,
  "Assessment #3614" => 3614,
  "Assessment #4877" => 4877,
  "TEST ASSESSMENT" => 4878,
  "Interoperability assessment - Electronic system for agricultural non-customs formalities (ELAN) package" => 4880,
  "Common templates for the transfer of information from national energy performance of buildings databases to the EU Building Stock Observatory" => 4881,
  "German short term rental data exchange law" => 4890,
  "Rifgts for EU citizens in non-EU countries " => 4891,
  "Commission Implementing Regulation (EU) 2025/1290 of 2 July 2025 laying down rules for the application of Regulation (EU) 2024/1157 of the European Parliament and of the Council" => 4899,
  "Emergency procedures in accordance with European Union product regulations " => 5003,
  "Digital Omnibus on AI" => 5157,
  "Digital Omnibus" => 5158,
  "Proposal for a REGULATION OF THE EUROPEAN PARLIAMENT AND OF THE COUNCIL  on the establishment of European Business Wallets" => 5159,
  "Proposal for a COUNCIL REGULATION amending Regulation (EU) No 904/2010" => 5164,
  "Proposal for a REGULATION OF THE EUROPEAN PARLIAMENT AND OF THE COUNCIL on establishing a framework of measures to facilitate the transport of military equipment, goods and personnel across the Union" => 5165,
  "Proposal for a DIRECTIVE OF THE EUROPEAN PARLIAMENT AND OF THE COUNCIL amending Directives (EU) 2016/2341 and 2016/97" => 5166,
  "Proposal for a REGULATION OF THE EUROPEAN PARLIAMENT AND OF THE COUNCIL amending Regulation (EU) 2019/1238 on a pan-European Personal Pension Product (PEPP)" => 5167,
  "Proposal for a REGULATION OF THE EUROPEAN PARLIAMENT AND OF THE COUNCIL amending Regulation (EU) 2019/2088, Regulation (EU) No 1286/2014, and Commission Delegated Regulation (EU) 2022/1288" => 5168,
  "Proposal for a REGULATION OF THE EUROPEAN PARLIAMENT AND OF THE COUNCIL establishing the Reparations Loan to Ukraine" => 5169,
  "asdasdasd" => 5205,
  "Commission Delegated Regulation (EU) 2025/1143 of 12 June 2025 " => 5317,
  "Commission Delegated Regulation (EU) 2025/1156 of 12 June 2025 " => 5321,
];
