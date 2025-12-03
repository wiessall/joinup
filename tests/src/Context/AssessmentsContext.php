<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Serialization\Yaml;
use Drupal\Core\Url;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\assessments\AssessmentInterface;
use Drupal\assessments\AssessmentVocabularyHelper;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\joinup\Traits\EntityTrait;
use Drupal\joinup\Traits\FileTrait;
use Drupal\joinup\Traits\TestingEntitiesTrait;
use Drupal\joinup\Traits\UtilityTrait;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Assert;

/**
 * Step definitions for assessments.
 */
class AssessmentsContext extends RawDrupalContext {

  use EntityTrait;
  use FileTrait;
  use UtilityTrait;
  use TestingEntitiesTrait;

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
   * @Given :type assessment(s):
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

      if (isset($assessment['binding_requirement'])) {
        $paragraph = $this->getEntityById('paragraph', $assessment['binding_requirement'], 'binding_requirement');
        $assessment['binding_requirement:target_id'] = $paragraph->id();
        $assessment['binding_requirement:target_revision_id'] = $paragraph->getRevisionId();
        unset($assessment['binding_requirement']);
      }

      if (isset($assessment['results_in'])) {
        $statement = $this->getEntityById('assessment_statement', $assessment['results_in']);
        $assessment[$statement->bundle() . ':target_id'] = $statement->id();
        unset($assessment['results_in']);
      }

      if (!empty($assessment['document'])) {
        $fileValues = [];
        foreach ($this->explodeDelimitedSeparatedStepArgument($assessment['document']) as $filename) {
          $file = $this->createFile($filename);
          $fileValues[] = [
            'target_id' => $file->id(),
          ];
        }
        $assessment['document'] = $fileValues;
      }

      // Resolve provider by name if provided (skip for report type).
      if ($type !== 'report' && isset($assessment['provider']) && !is_numeric($assessment['provider'])) {
        $provider = $this->getEntityByLabel('assessment', $assessment['provider'], 'public_organisation');
        $assessment['provider'] = $provider->id();
      }

      // Resolve timestamp fields.
      foreach (['created', 'changed'] as $field) {
        if (!empty($assessment[$field]) && !is_numeric($assessment[$field])) {
          $assessment[$field] = strtotime($assessment[$field]);
        }
      }

      $assessment = (object) $assessment;

      $assessment->type = $type;
      $this->parseEntityFields('assessment', $assessment);
      $this->getDriver()->createEntity('assessment', $assessment);
    }
  }

  /**
   * Adds a binding requirement paragraph to a report assessment.
   *
   * Table format (vertical):
   * | report      | Report Name     |
   * | expression  | Expression Name |
   * | service     | Service Name    |
   * | description | Description     |
   *
   * @param \Behat\Gherkin\Node\TableNode $bindingTable
   *   The table with binding requirement data.
   *
   * @Given the following report has binding requirement:
   */
  public function addBindingRequirementToReport(TableNode $bindingTable): void {
    $binding = $bindingTable->getRowsHash();

    $report = $this->getEntityByLabel('assessment', $binding['report'], 'report');

    $expression_id = NULL;
    $service_id = NULL;

    if (!empty($binding['expression'])) {
      $expression = $this->getEntityByLabel('assessment', $binding['expression'], 'expression');
      $expression_id = $expression->id();
    }

    if (!empty($binding['service'])) {
      $service = $this->getEntityByLabel('assessment', $binding['service'], 'public_service');
      $service_id = $service->id();
    }

    $description = $binding['description'] ?? 'Test requirement description';
    $paragraph = \Drupal::entityTypeManager()
      ->getStorage('paragraph')
      ->create([
        'type' => 'binding_requirement',
        'description' => ['value' => $description, 'format' => 'plain_text'],
        'expression' => $expression_id ? [$expression_id] : [],
        'has_related_service' => $service_id ? [$service_id] : [],
      ]);
    $paragraph->save();

    $this->entities['paragraph'][$paragraph->id()] = $paragraph;

    $report->set('binding_requirement', [
      [
        'target_id' => $paragraph->id(),
        'target_revision_id' => $paragraph->getRevisionId(),
      ],
    ]);
    $report->save();
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
   * Creates assessment statements.
   *
   * @param \Behat\Gherkin\Node\TableNode $table
   *   The table with the data for the fields of the assessment statement.
   *
   * @Given (the following )assessment statement(s):
   */
  public function createAssessmentStatements(TableNode $table): void {
    foreach ($table->getColumnsHash() as $values) {
      assert(in_array($values['statement_type'], ['legal', 'technical', 'other'], TRUE), 'Invalid assessment statement type provided.');
      $values['type'] = $values['statement_type'];
      $values = (object) $values;

      if (!empty($values->landing_page)) {
        $fileValues = [];
        foreach ($this->explodeDelimitedSeparatedStepArgument($values->landing_page) as $filename) {
          $file = $this->createFile($filename);
          $fileValues[] = [
            'target_id' => $file->id(),
          ];
        }
        $values->landing_page = $fileValues;
      }

      $this->parseEntityFields('assessment_statement', $values);
      $this->getDriver()->createEntity('assessment_statement', $values);
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

  /**
   * Adds the assessments-created solution's collector.
   *
   * @BeforeScenario @assessments&&@api
   */
  public function beforeAssessmentsScenario(): void {
    \Drupal::entityTypeManager()->getStorage('node')->create([
      'type' => 'collection',
      'title' => 'Solutions mentioned in an assessment',
      'uuid' => AssessmentInterface::SOLUTION_COLLECTOR,
      'field_state' => 'published',
    ])->save();
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
