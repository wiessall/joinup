<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Serialization\Yaml;
use Drupal\Core\Url;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\assessments\AssessmentInterface;
use Drupal\assessments\AssessmentVocabularyHelper;
use Drupal\assessments\Entity\AssessmentStatement;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\joinup\Traits\AssertTrait;
use Drupal\joinup\Traits\EntityTrait;
use Drupal\joinup\Traits\FileTrait;
use Drupal\joinup\Traits\UtilityTrait;
use Drupal\user\UserInterface;

/**
 * Step definitions for assessments.
 */
class AssessmentsContext extends RawDrupalContext {

  use AssertTrait;
  use EntityTrait;
  use FileTrait;
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
   */
  #[Given(':type assessment(s):')]
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
   * | report                | Report Name              |
   * | expression            | Expression Name          |
   * | service               | Service Name             |
   * | participation         | Participation Name       |
   * | description           | Description              |
   * | documented_in         | https://example.com      |
   * | asset                 | Solution Name            |
   * | requirement_documents | text.pdf                 |
   *
   * @param \Behat\Gherkin\Node\TableNode $bindingTable
   *   The table with binding requirement data.
   */
  #[Given('the following report has binding requirement:')]
  public function addBindingRequirementToReport(TableNode $bindingTable): void {
    $binding = $bindingTable->getRowsHash();

    $report = $this->getEntityByLabel('assessment', $binding['report'], 'report');

    $expression_id = NULL;
    $service_id = NULL;
    $participation_id = NULL;

    if (!empty($binding['expression'])) {
      $expression = $this->getEntityByLabel('assessment', $binding['expression'], 'expression');
      $expression_id = $expression->id();
    }

    if (!empty($binding['service'])) {
      $service = $this->getEntityByLabel('assessment', $binding['service'], 'public_service');
      $service_id = $service->id();
    }

    if (!empty($binding['participation'])) {
      $participation = $this->getEntityByLabel('assessment', $binding['participation'], 'participation');
      $participation_id = $participation->id();
    }

    $description = $binding['description'] ?? 'Test requirement description';
    $values = [
      'type' => 'binding_requirement',
      'description' => ['value' => $description, 'format' => 'plain_text'],
      'expression' => $expression_id ? [$expression_id] : [],
      'affects' => $service_id ? [$service_id] : [],
      'has_participation' => $participation_id ? [$participation_id] : [],
    ];

    if (!empty($binding['documented_in'])) {
      $values['documented_in'] = ['uri' => $binding['documented_in']];
    }

    if (!empty($binding['asset'])) {
      $node = $this->getEntityByLabel('node', $binding['asset'], 'solution');
      $values['asset'] = [$node->id()];
    }

    if (!empty($binding['requirement_documents'])) {
      $file = $this->createFile($binding['requirement_documents']);
      $values['requirement_documents'] = [['target_id' => $file->id()]];
    }

    $paragraph = \Drupal::entityTypeManager()
      ->getStorage('paragraph')
      ->create($values);
    $paragraph->save();

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
   */
  #[Given('the assessments collection exists')]
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
   */
  #[Given('(the following )assessment statement(s):')]
  public function createAssessmentStatements(TableNode $table): void {
    foreach ($table->getColumnsHash() as $values) {
      assert(in_array($values['statement_type'], ['legal', 'organisational', 'semantic', 'technical'], TRUE), 'Invalid assessment statement type provided.');
      $values['type'] = $values['statement_type'];
      $values = (object) $values;

      if (!empty($values->interpretation)) {
        $tids = \Drupal::entityQuery('taxonomy_term')->accessCheck(FALSE)
          ->condition('vid', 'assess_statement_interpretation')
          ->condition('id', $values->interpretation)
          ->execute();
        assert(count($tids) === 1);
        $values->interpretation = reset($tids);
      }

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
   */
  #[Then('a GET request to :path should return the following JSON:')]
  public function assertResponseJson($path, PyStringNode $expectedJsonAsYaml): void {
    $baseUrl = $this->getMinkParameter('base_url');
    $replacements = ['__base_url__' => $baseUrl];
    $expected = Yaml::decode(strtr($expectedJsonAsYaml->getRaw(), $replacements));

    $this->visitPath($path);
    $actual = Json::decode($this->getSession()->getPage()->getContent());

    $this->assertSameValues($expected, $actual);
  }

  /**
   * Navigates to the moderate form of a specific assessment report.
   *
   * @param string $title
   *   The title of the assessment report.
   */
  #[When('I go to the moderate form of the :title assessment report')]
  public function visitAssessmentReportModerateForm(string $title): void {
    $entity = $this->getEntityByLabel('assessment', $title, 'report');
    $url = Url::fromRoute('assessments.moderate', [
      'assessment_type' => $entity->bundle(),
      'assessment' => $entity->id(),
    ]);
    $this->visitPath($url->toString());
  }

  /**
   * Navigates to the translation overview form of an assessment.
   *
   * @param string $title
   *   The title of the entity.
   * @param string $bundle
   *   The assessment bundle.
   */
  #[When('I visit the translation overview form of the :title :bundle assessment')]
  public function visitEntityTranslationOverviewForm(string $title, string $bundle): void {
    $entity = $this->getEntityByLabel('assessment', $title, $bundle);
    $this->visitPath($entity->toUrl('drupal:content-translation-overview')->toString());
  }

  /**
   * Checks if an assessment entity with a specific name exists.
   *
   * @param string $type
   *   The assessment bundle type (e.g., 'expression', 'report').
   * @param string $name
   *   The name of the assessment entity.
   */
  #[Then('the :type assessment with name :name should exist')]
  public function assertAssessmentWithNameExists(string $type, string $name): void {
    $storage = \Drupal::entityTypeManager()->getStorage('assessment');
    $entities = $storage->loadByProperties([
      'type' => $type,
      'name' => $name,
    ]);
    $this->assertCondition(!empty($entities), sprintf(
      'No assessment of type "%s" with name "%s" found.',
      $type,
      $name
    ));
  }

  /**
   * Clicks on the ID link for a specific assessment in the admin listing.
   *
   * @param string $name
   *   The name of the assessment.
   * @param string $bundle
   *   The assessment bundle type.
   */
  #[When('I click the ID link for the :name :bundle assessment')]
  #[When('I click the ID link for the :name assessment')]
  public function clickAssessmentIdLink(string $name, string $bundle = 'report'): void {
    $assessment = $this->getEntityByLabel('assessment', $name, $bundle);
    $id = $assessment->id();

    $page = $this->getSession()->getPage();
    $link = $page->findLink((string) $id);

    $this->assertCondition(!empty($link), sprintf('Could not find ID link "%s" for assessment "%s"', $id, $name));
    $link->click();
  }

  /**
   * Asserts "New assessment report to be reviewed" email with ID in subject.
   *
   * Table format:
   * | assessment | Assessment Name |
   * | recipient  | username        |
   * | subject    | Subject prefix  |
   * | body       | Body text       |
   *
   * @param \Behat\Gherkin\Node\TableNode $table
   *   The email data table.
   */
  #[Then('the following assessment report submitted email should have been sent:')]
  public function assertAssessmentReportSubmittedEmail(TableNode $table): void {
    $data = $table->getRowsHash();
    $entity = $this->getEntityByLabel('assessment', $data['assessment'], 'report');
    $expected_subject = $data['subject'] . ' (' . $entity->id() . ')';

    $user = user_load_by_name($data['recipient']);
    $recipient_email = $user->getEmail();

    $captured_emails = \Drupal::state()->get('system.test_mail_collector', []);

    foreach ($captured_emails as $mail) {
      if ($mail['to'] !== $recipient_email || $mail['subject'] !== $expected_subject) {
        continue;
      }

      $mail_body = (string) $mail['body'];
      if ($mail['body'] instanceof MarkupInterface) {
        $mail_body = strip_tags($mail_body);
        $mail_body = htmlspecialchars_decode($mail_body, ENT_QUOTES | ENT_SUBSTITUTE);
      }

      $mail_body = preg_replace("/\s+/", ' ', trim($mail_body));
      $body = preg_replace("/\s+/", ' ', $data['body']);

      if (str_contains($mail_body, $body)) {
        return;
      }
    }

    throw new \Exception("Email not found to '$recipient_email' with subject '$expected_subject' and body '{$data['body']}'.");
  }

  /**
   * Creates assessment statements of given types and links them to a report.
   *
   * Table format:
   * | type           |
   * | legal          |
   * | organisational |
   *
   * @param string $name
   *   The report name.
   * @param \Behat\Gherkin\Node\TableNode $table
   *   Single-column table with a 'type' header listing statement types.
   */
  #[Given('the :name report has assessment statements of types:')]
  public function addAssessmentStatementsToReport(string $name, TableNode $table): void {
    $report = $this->getEntityByLabel('assessment', $name, 'report');
    foreach ($table->getHash() as $row) {
      $type = $row['type'];
      $statement = AssessmentStatement::create(['type' => $type]);
      $statement->save();
      $report->get($type)->setValue(['target_id' => $statement->id()]);
    }
    $report->save();
  }

  /**
   * Asserts that the PDF HTML for a report contains the given strings.
   *
   * @param string $name
   *   The report name.
   * @param \Behat\Gherkin\Node\TableNode $table
   *   A table of strings expected in the PDF HTML.
   */
  #[Then('the PDF HTML for the :name report should contain:')]
  public function assertPdfHtmlContains(string $name, TableNode $table): void {
    $report = $this->getEntityByLabel('assessment', $name, 'report');
    $html = \Drupal::service('entity_print.print_builder')->printHtml($report, FALSE, FALSE);
    foreach ($table->getColumn(0) as $text) {
      $this->assertCondition(str_contains($html, $text), "The text '$text' was not found in the PDF HTML.");
    }
  }

  /**
   * Asserts that the PDF HTML for a report does not contain the given strings.
   *
   * @param string $name
   *   The report name.
   * @param \Behat\Gherkin\Node\TableNode $table
   *   A table of strings not expected in the PDF HTML.
   */
  #[Then('the PDF HTML for the :name report should not contain:')]
  public function assertPdfHtmlNotContains(string $name, TableNode $table): void {
    $report = $this->getEntityByLabel('assessment', $name, 'report');
    $html = \Drupal::service('entity_print.print_builder')->printHtml($report, FALSE, FALSE);
    foreach ($table->getColumn(0) as $text) {
      $this->assertCondition(!str_contains($html, $text), "The text '$text' was found in the PDF HTML.");
    }
  }

  /**
   * Asserts that no participation entities exist for a given assessment report.
   *
   * @param string $title
   *   The title of the assessment report.
   */
  #[Then('no participation entities should exist for the :title assessment report')]
  public function assertNoParticipationEntitiesExist(string $title): void {
    $report = $this->getEntityByLabel('assessment', $title, 'report');

    $binding_requirements = $report->get('binding_requirement')->referencedEntities();
    foreach ($binding_requirements as $paragraph) {
      $participations = $paragraph->get('has_participation')->referencedEntities();
      $this->assertCondition(
        empty($participations),
        sprintf(
          'Expected no participation entities for report "%s", but found %d.',
          $title,
          count($participations)
        )
      );
    }
  }

}
