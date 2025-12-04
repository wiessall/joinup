<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Serialization\Yaml;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\assessments\AssessmentInterface;
use Drupal\assessments\AssessmentVocabularyHelper;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Assert;

/**
 * Step definitions for assessments.
 */
class AssessmentsContext extends RawDrupalContext {

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
   * Asserts that a GET request returns an expected array serialized as JSON.
   *
   * @param string $path
   *   The path of the GET request to be checked.
   * @param \Behat\Gherkin\Node\PyStringNode $expectedJsonAsYaml
   *   The expected JSON. For convenience, the value should be serialized with
   *   YAML encoding as it offers better developer experience (DX).
   *
   * @Then a GET request to :path should return the following JSON:
   */
  public function assertResponseJson($path, PyStringNode $expectedJsonAsYaml): void {
    $baseUrl = $this->getMinkParameter('base_url');
    $replacements = ['__base_url__' => $baseUrl];
    $expected = Yaml::decode(strtr($expectedJsonAsYaml->getRaw(), $replacements));

    $this->visitPath($path);
    $actual = Json::decode($this->getSession()->getPage()->getContent());

    Assert::assertEquals($expected, $actual);
  }

}
