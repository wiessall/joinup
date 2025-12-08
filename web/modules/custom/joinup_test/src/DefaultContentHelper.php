<?php

declare(strict_types=1);

namespace Drupal\joinup_test;

use Drupal\assessments\AssessmentInterface;
use Drupal\joinup_collection\JoinupCollectionHelper;
use Drupal\pathauto\PathautoState;

/**
 * Provides default content for testing.
 */
final class DefaultContentHelper {

  /**
   * Returns default collections.
   */
  public static function getDefaultCollections(): array {
    return [
      // Portal support collection.
      JoinupCollectionHelper::JOINUP_COLLECTION_UUID => [
        'title' => 'Portal support',
        'uri' => JoinupCollectionHelper::JOINUP_COLLECTION_URI,
        'field_state' => 'published',
        'field_topic' => \Drupal::getContainer()->get('topic')->getTopicByName('demography'),
        'created' => strtotime('2015-01-01 08:00'),
        'changed' => strtotime('2015-01-01 08:00'),
        'published_at' => strtotime('2015-01-01 08:00'),
        'abstract' => 'Portal support is a collaborative platform created by the European Commission and funded by the European Union via the ISA² Programme.',
        'description' => [
          'value' => 'Portal support is a collaborative platform created by the European Commission and funded by the European Union via the ISA² Programme. It offers several services that aim to help e-Government professionals share their experience with each other. The platform is also a repository of e-Government solutions, case studies, reusable software and components, and best practices. Portal support is a key initiative of the European Commission’s ISA² Programme, which is part of the EU’s policy to create a Digital Single Market.',
          'format' => 'content_editor',
        ],
        'path' => [
          'alias' => '/collection/portal-support',
          'pathauto' => PathautoState::SKIP,
        ],
      ],
      // The legacy group that the webform assessments are linked to.
      AssessmentInterface::GROUP_UUID => [
        'title' => 'Interoperability assessments space',
        'uri' => 'https://example.com/assessments',
        'short_id' => 'assessments',
        'field_state' => 'published',
        'created' => strtotime('2015-01-01 08:00'),
        'changed' => strtotime('2015-01-01 08:00'),
        'published_at' => strtotime('2015-01-01 08:00'),
        'abstract' => 'Some nice space for assessments.',
        'description' => [
          'value' => 'Some nice space for assessments.',
          'format' => 'content_editor',
        ],
        'path' => [
          'alias' => '/collection/assessments',
        ],
      ],
      // The solution collector for the created assets through assessments.
      AssessmentInterface::SOLUTION_COLLECTOR => [
        'title' => 'Assessment reports',
        'uri' => 'https://example.com/assessment-reports',
        'short_id' => 'assessment-reports',
        'field_state' => 'published',
        'created' => strtotime('2015-01-01 08:00'),
        'changed' => strtotime('2015-01-01 08:00'),
        'published_at' => strtotime('2015-01-01 08:00'),
        'abstract' => 'Some nice space for assessment reports.',
        'description' => [
          'value' => 'Some nice space for assessment reports.',
          'format' => 'content_editor',
        ],
        'path' => [
          'alias' => '/collection/assessment-reports',
          'pathauto' => PathautoState::SKIP,
        ],
      ],
    ];
  }

}
