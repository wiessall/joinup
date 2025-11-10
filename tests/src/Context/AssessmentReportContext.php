<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Gherkin\Node\TableNode;
use Drupal\assessments\Entity\Assessment;
use Drupal\Core\Url;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\EntityTrait;
use Drupal\joinup\Traits\TestingEntitiesTrait;
use Drupal\joinup\Traits\UserTrait;
use Drupal\joinup\Traits\UtilityTrait;

/**
 * Step definitions for assessment report entities.
 */
class AssessmentReportContext extends RawDrupalContext {

  use EntityTrait;
  use TestingEntitiesTrait;
  use UserTrait;
  use UtilityTrait;

  /**
   * Creates assessment entities of a given bundle.
   *
   * @param string $bundle
   *   The assessment bundle (e.g., 'report').
   * @param \Behat\Gherkin\Node\TableNode $assessmentTable
   *   The table of assessment data.
   *
   * @Given :bundle assessment content:
   */
  public function createAssessmentContent(string $bundle, TableNode $assessmentTable): void {
    foreach ($assessmentTable->getHash() as $assessmentHash) {
      $values = [
        'type' => $bundle,
      ];

      foreach ($assessmentHash as $field => $value) {
        switch ($field) {
          case 'author':
            $user = $this->getUserByName($value);
            $values['uid'] = $user->id();
            break;

          case 'created':
          case 'changed':
            $values[$field] = strtotime($value);
            break;

          default:
            $values[$field] = $value;
            break;
        }
      }

      $assessment = Assessment::create($values);
      $assessment->save();

      $this->entities['assessment'][$assessment->id()] = $assessment;
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
