<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Gherkin\Node\TableNode;
use Drupal\Core\Url;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\assessments\AssessmentInterface;
use Drupal\assessments\AssessmentVocabularyHelper;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\user\UserInterface;
use Drupal\assessments\Entity\Assessment;
use Drupal\joinup\Traits\EntityTrait;
use Drupal\joinup\Traits\TestingEntitiesTrait;
use Drupal\joinup\Traits\UserTrait;
use Drupal\joinup\Traits\UtilityTrait;

/**
 * Step definitions for assessments.
 */
class AssessmentsContext extends RawDrupalContext {

  use EntityTrait;
  use TestingEntitiesTrait;
  use UserTrait;
  use UtilityTrait;

  /**
   * Programmatically creates assessment contents.
   *
   * Table format:
   * | name   | <field n>       |
   * | <name> | <field n value> |
   *
   * @param string $type
   *   The bundle machine name.
   * @param \Behat\Gherkin\Node\TableNode $assessmentsTable
   *   The table with the data for the fields of the assessment.
   *
   * @Given :type assessments:
   */
  public function createAssessments(string $type, TableNode $assessmentsTable): void {
    foreach ($assessmentsTable->getHash() as $assessment) {
      $vocabHelper = \Drupal::service(AssessmentVocabularyHelper::class);

      $fieldVocabMap = [
        'public_organisation' => ['organisation_type' => 'assess_provider_type'],
        'participation' => ['participant_type' => 'assess_participant_type'],
        'public_service' => ['thematic_area' => 'assess_public_service_area'],
      ];

      foreach ($fieldVocabMap[$type] ?? [] as $fieldName => $vid) {
        if (isset($assessment[$fieldName])) {
          $assessment[$fieldName] = $vocabHelper->getTermFromIdentifier($vid, $assessment[$fieldName])->label();
        }
      }

      // Resolve username passed to uid.
      if (isset($assessment['uid']) && !is_numeric($assessment['uid'])) {
        $user = user_load_by_name($assessment['uid']);
        if ($user instanceof UserInterface) {
          $assessment['uid'] = $user->id();
        }
      }

      $assessment = (object) $assessment;

      $assessment->type = $type;
      $this->parseEntityFields('assessment', $assessment);
      $this->getDriver()->createEntity('assessment', $assessment);
    }
  }

  /**
   * Creates the assessment collection if it doesn't exist yet.
   *
   * @Given the assessments collection exists
   */
  public function createAssessmentsCollection(): void {
    $collection = \Drupal::service('entity.repository')->loadEntityByUuid('node', AssessmentInterface::GROUP_UUID);
    if (!$collection instanceof CollectionInterface) {
      $this->nodeCreate((object) [
        'type' => 'collection',
        'uuid' => AssessmentInterface::GROUP_UUID,
        'title' => 'Interoperability assessments space',
        'short_id' => 'assessments',
        'field_state' => 'published',
      ]);
    }
  }

  /**
   * Navigates to the moderate form of a specific assessment report.
   *
   * @param string $title
   *   The title of the assessment report.
   *
   * @When I go to the moderate form of the :title assessment report
   */
  public function visitAssessmentReportModerateForm(string $title): void {
    $entity = $this->getEntityByLabel('assessment', $title, 'report');
    $url = Url::fromRoute('entity.assessment.moderate_form', [
      'assessment' => $entity->id(),
    ]);
    $this->visitPath($url->toString());
  }

}
