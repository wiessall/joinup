<?php

/**
 * @file
 * Deploy functions for Joinup.
 *
 * This should only contain update functions that rely on the Drupal API and
 * need to run _after_ the configuration is imported.
 *
 * This is applicable in most cases. However, in case the update code enables
 * some functionality that is required for configuration to be successfully
 * imported, it should instead be placed in joinup_core.post_update.php.
 */

declare(strict_types = 1);

use Drupal\pathauto\PathautoState;

/**
 * Fix Interoperable Europe news creation link.
 */
function joinup_core_deploy_108900(): void {
  $entityTypeManager = \Drupal::entityTypeManager();
  $entityTypeManager->getStorage('node')->load(704825)->set('path', [
    'pathauto' => PathautoState::SKIP,
    'alias' => '/interoperable-europe/latest-news',
  ])->save();
  if ($alias = $entityTypeManager->getStorage('path_alias')->load(84170)) {
    $alias->delete();
  }
}

/**
 * Update the topic vocabulary terms.
 */
function joinup_core_deploy_108901(array &$sandbox): string {
  if (empty($sandbox['tasks'])) {
    $sandbox['tasks'] = [
      'createTopicTerm' => [
        [
          'Digital government',
          NULL,
        ],
        [
          'Energy and environment',
          NULL,
        ],
        [
          'Energy and environment',
          'http://joinup.eu/ontology/topic/category#energy-and-environment',
        ],
        [
          'Smart cities',
          'http://joinup.eu/ontology/topic/category#digital-government',
        ],
        [
          'eJustice and law',
          'http://joinup.eu/ontology/topic/category#digital-government',
        ],
      ],
      'moveTopicChildrenToNewParent' => [
        [
          'source' => 'http://joinup.eu/ontology/topic/category#egovernment',
          'destination' => 'http://joinup.eu/ontology/topic/category#digital-government',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#energy',
          'destination' => 'http://joinup.eu/ontology/topic/category#energy-and-environment',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#environment',
          'destination' => 'http://joinup.eu/ontology/topic/category#energy-and-environment',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#industry-and-production',
          'destination' => 'http://joinup.eu/ontology/topic/category#economics',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#information',
          'destination' => 'http://joinup.eu/ontology/topic/category#ict',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#law',
          'destination' => 'http://joinup.eu/ontology/topic/category#digital-government',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#science-and-technology',
          'destination' => 'http://joinup.eu/ontology/topic/category#education',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#social',
          'destination' => 'http://joinup.eu/ontology/topic/category#digital-government',
        ],
      ],
      'renameTopic' => [
        [
          'http://joinup.eu/ontology/topic#free-and-open-source-software-in-public-administrations',
          'Free and open source software',
        ],
        [
          'http://joinup.eu/ontology/topic#ict-standardisation-in-egovernment',
          'ICT standardisation',
        ],
        [
          'http://joinup.eu/ontology/topic#statistics',
          'Statistics and information',
        ],
        [
          'http://joinup.eu/ontology/topic#culture',
          'Culture and education',
        ],
      ],
      'changeTopicId' => [
        // All couples that have their topic IDs changed, also appear in the
        // updateDbReference below.
        [
          'http://joinup.eu/ontology/topic#free-and-open-source-software-in-public-administrations',
          'http://joinup.eu/ontology/topic#free-and-open-source-software',
        ],
        [
          'http://joinup.eu/ontology/topic#ict-standardisation-in-egovernment',
          'http://joinup.eu/ontology/topic#ict-standardisation',
        ],
        [
          'http://joinup.eu/ontology/topic#statistics',
          'http://joinup.eu/ontology/topic#statistics-and-informatino',
        ],
        [
          'http://joinup.eu/ontology/topic#culture',
          'http://joinup.eu/ontology/topic#culture-and-education',
        ],
      ],
      'changeTopicParent' => [
        [
          'http://joinup.eu/ontology/topic#business-and-competition',
          'http://joinup.eu/ontology/topic/category#digital-government',
        ],
        [
          'http://joinup.eu/ontology/topic#free-and-open-source-software',
          'http://joinup.eu/ontology/topic/category#ict',
        ],
        [
          'http://joinup.eu/ontology/topic#content-and-knowledge-management',
          'http://joinup.eu/ontology/topic/category#ict',
        ],
        [
          'http://joinup.eu/ontology/topic#statistics',
          'http://joinup.eu/ontology/topic/category#ict',
        ],
        [
          'http://joinup.eu/ontology/topic#european-policies',
          'http://joinup.eu/ontology/topic/category#digital-government',
        ],
        [
          'http://joinup.eu/ontology/topic#eparticipation-and-evoting',
          'http://joinup.eu/ontology/topic/category#digital-government',
        ],
        [
          'http://joinup.eu/ontology/topic#ict-standardisation',
          'http://joinup.eu/ontology/topic/category#ict',
        ],
        [
          'http://joinup.eu/ontology/topic#science-and-technology',
          'http://joinup.eu/ontology/topic/category#education',
        ],
        [
          'http://joinup.eu/ontology/topic#demography-and-population',
          'http://joinup.eu/ontology/topic/category#digital-government',
        ],
        [
          'http://joinup.eu/ontology/topic#einclusion',
          'http://joinup.eu/ontology/topic/category#digital-government',
        ],
        [
          'http://joinup.eu/ontology/topic#social',
          'http://joinup.eu/ontology/topic/category#digital-government',
        ],
        [
          'http://joinup.eu/ontology/topic#geospatial',
          'http://joinup.eu/ontology/topic/category#transport',
        ],
      ],
      'updateDbReference' => [
        [
          'source' => 'http://joinup.eu/ontology/topic#defence',
          'destination' => 'http://joinup.eu/ontology/topic#ict-security',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#economic-analysis',
          'destination' => 'http://joinup.eu/ontology/topic#banking-and-financial-services',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#eu-finance',
          'destination' => 'http://joinup.eu/ontology/topic#banking-and-financial-services',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#free-and-open-source-software-in-public-administrations',
          'destination' => 'http://joinup.eu/ontology/topic#free-and-open-source-software',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#ict-standardisation-in-egovernment',
          'destination' => 'http://joinup.eu/ontology/topic#ict-standardisation',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#open-government',
          'destination' => 'http://joinup.eu/ontology/topic#egovernment',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#energy',
          'destination' => 'http://joinup.eu/ontology/topic#energy-and-environment',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#environment',
          'destination' => 'http://joinup.eu/ontology/topic#energy-and-environment',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#authorisation-and-authentication',
          'destination' => 'http://joinup.eu/ontology/topic#eidentity-and-esignature',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#information',
          'destination' => 'http://joinup.eu/ontology/topic#content-and-knowledge-management',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#security',
          'destination' => 'http://joinup.eu/ontology/topic#ict-security',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#politics',
          'destination' => 'http://joinup.eu/ontology/topic#european-policies',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#statistics',
          'destination' => 'http://joinup.eu/ontology/topic#statistics-and-informatino',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#education',
          'destination' => 'http://joinup.eu/ontology/topic#culture-and-education',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#law',
          'destination' => 'http://joinup.eu/ontology/topic#ejustice-and-law',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#ejustice',
          'destination' => 'http://joinup.eu/ontology/topic#ejustice-and-law',
        ],
      ],
      'createRedirect' => [
        [
          'source' => 'http://joinup.eu/ontology/topic/category#egovernment',
          'destination' => 'http://joinup.eu/ontology/topic/category#digital-government',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#energy',
          'destination' => 'http://joinup.eu/ontology/topic/category#energy-and-environment',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#environment',
          'destination' => 'http://joinup.eu/ontology/topic/category#energy-and-environment',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#industry-and-production',
          'destination' => 'http://joinup.eu/ontology/topic/category#economics',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#information',
          'destination' => 'http://joinup.eu/ontology/topic/category#ict',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#law',
          'destination' => 'http://joinup.eu/ontology/topic/category#digital-government',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#science-and-technology',
          'destination' => 'http://joinup.eu/ontology/topic/category#education',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#social',
          'destination' => 'http://joinup.eu/ontology/topic/category#digital-government',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#defence',
          'destination' => 'http://joinup.eu/ontology/topic#ict-security',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#economic-analysis',
          'destination' => 'http://joinup.eu/ontology/topic#banking-and-financial-services',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#eu-finance',
          'destination' => 'http://joinup.eu/ontology/topic#banking-and-financial-services',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#open-government',
          'destination' => 'http://joinup.eu/ontology/topic#egovernment',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#energy',
          'destination' => 'http://joinup.eu/ontology/topic#energy-and-environment',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#environment',
          'destination' => 'http://joinup.eu/ontology/topic#energy-and-environment',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#authorisation-and-authentication',
          'destination' => 'http://joinup.eu/ontology/topic#eidentity-and-esignature',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#information',
          'destination' => 'http://joinup.eu/ontology/topic#content-and-knowledge-management',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#security',
          'destination' => 'http://joinup.eu/ontology/topic#ict-security',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#politics',
          'destination' => 'http://joinup.eu/ontology/topic#european-policies',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#geospatial',
          'destination' => 'http://joinup.eu/ontology/topic/category#transport',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#law',
          'destination' => 'http://joinup.eu/ontology/topic#ejustice-and-law',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#ejustice',
          'destination' => 'http://joinup.eu/ontology/topic#ejustice-and-law',
        ],
      ],
      // All terms should be empty in references/children after the migration.
      'deleteTopicTerm' => [
        'http://joinup.eu/ontology/topic#authorisation-and-authentication',
        'http://joinup.eu/ontology/topic#defence',
        'http://joinup.eu/ontology/topic#economic-analysis',
        'http://joinup.eu/ontology/topic#education',
        'http://joinup.eu/ontology/topic#ejustice',
        'http://joinup.eu/ontology/topic#energy',
        'http://joinup.eu/ontology/topic#environment',
        'http://joinup.eu/ontology/topic#eu-finance',
        'http://joinup.eu/ontology/topic#information',
        'http://joinup.eu/ontology/topic#law',
        'http://joinup.eu/ontology/topic#open-government',
        'http://joinup.eu/ontology/topic#politics',
        'http://joinup.eu/ontology/topic#security',
        'http://joinup.eu/ontology/topic/category#business-and-competition',
        'http://joinup.eu/ontology/topic/category#defence',
        'http://joinup.eu/ontology/topic/category#egovernment',
        'http://joinup.eu/ontology/topic/category#energy',
        'http://joinup.eu/ontology/topic/category#environment',
        'http://joinup.eu/ontology/topic/category#geospatial',
        'http://joinup.eu/ontology/topic/category#industry-and-production',
        'http://joinup.eu/ontology/topic/category#information',
        'http://joinup.eu/ontology/topic/category#law',
        'http://joinup.eu/ontology/topic/category#politics',
        'http://joinup.eu/ontology/topic/category#science-and-technology',
        'http://joinup.eu/ontology/topic/category#social',
      ],
    ];

    $sandbox['current'] = 0;
    $sandbox['max'] = 0;
    foreach ($sandbox['tasks'] as $data) {
      $sandbox['max'] += count($data);
    }
  }

  $vocab_manager = \Drupal::getContainer()->get('topic.vocabulary_manager');
  $operation = array_key_first($sandbox['tasks']);
  $operations_per_batch = in_array($operation, ['createRedirect', 'deleteTopicTerm']) ? 1 : 10;
  $tasks = array_splice($sandbox['tasks'][$operation], 0, $operations_per_batch);
  if (empty($sandbox['tasks'][$operation])) {
    unset($sandbox['tasks'][$operation]);
  }

  foreach ($tasks as $task) {
    switch ($operation) {
      case 'createTopicTerm':
      case 'renameTopic':
      case 'changeTopicId':
      case 'changeTopicParent':
        $vocab_manager->{$operation}($task[0], $task[1]);
        break;

      case 'moveTopicChildrenToNewParent':
      case 'updateDbReference':
      case 'createRedirect':
        $vocab_manager->{$operation}($task['source'], $task['destination']);
        break;

      case 'deleteTopicTerm':
        $vocab_manager->{$operation}($task);
        break;

      default:
        break;
    }
    $sandbox['current']++;
  }

  $sandbox['#finished'] = empty($sandbox['tasks']) ? 1 : ($sandbox['current'] / $sandbox['max']);
  return "Topic refactoring: $operation, " . count($tasks) . " tasks have been completed. Total progress: {$sandbox['current']} / {$sandbox['max']}";
}
