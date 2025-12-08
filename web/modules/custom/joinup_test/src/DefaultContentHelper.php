<?php

declare(strict_types=1);

namespace Drupal\joinup_test;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\assessments\AssessmentInterface;
use Drupal\joinup_collection\JoinupCollectionHelper;
use Drupal\pathauto\PathautoState;

/**
 * Provides default content for testing.
 */
final class DefaultContentHelper {

  public function __construct(
    protected EntityRepositoryInterface $entityRepository,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Returns the default contact entity or creates one.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The default contact entity.
   */
  public function getDefaultContact(): EntityInterface {
    $contact = $this->entityRepository->loadEntityByUuid('node', JoinupCollectionHelper::JOINUP_CONTACT_UUID);
    if (!$contact) {
      $contact = $this->entityTypeManager->getStorage('node')->create([
        'type' => 'contact_information',
        'uuid' => JoinupCollectionHelper::JOINUP_CONTACT_UUID,
        'title' => 'Contact name',
        'email' => 'contact.email@example.com',
      ]);
      $contact->save();
    }
    return $contact;
  }

  /**
   * Returns the default owner entity or creates one.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The default owner entity.
   */
  public function getDefaultOwner(): EntityInterface {
    $owner = $this->entityRepository->loadEntityByUuid('node', JoinupCollectionHelper::JOINUP_OWNER_UUID);
    if (!$owner) {
      $owner = $this->entityTypeManager->getStorage('node')->create([
        'type' => 'owner',
        'uuid' => JoinupCollectionHelper::JOINUP_OWNER_UUID,
        'title' => 'ISA²',
      ]);
      $owner->save();
    }
    return $owner;
  }

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
        'uri' => 'http://data.europa.eu/w21/c5e99eae-6d31-47a0-b914-a4b9f4ea82cf',
        'short_id' => 'assessments',
        'field_state' => 'published',
        'created' => strtotime('2015-01-01 08:01'),
        'changed' => strtotime('2015-01-01 08:01'),
        'published_at' => strtotime('2015-01-01 08:01'),
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
        'uri' => 'http://data.europa.eu/w21/e0f3b87b-9de3-48d5-8fff-5ec88ccd1a71',
        'short_id' => 'assessment-reports',
        'field_state' => 'published',
        'created' => strtotime('2015-01-01 08:02'),
        'changed' => strtotime('2015-01-01 08:02'),
        'published_at' => strtotime('2015-01-01 08:02'),
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
