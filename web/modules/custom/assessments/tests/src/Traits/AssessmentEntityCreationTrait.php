<?php

declare(strict_types=1);

namespace Drupal\Tests\assessments\Traits;

use Drupal\assessments\AssessmentInterface;
use Drupal\assessments\AssessmentVocabularyHelper;
use Drupal\assessments\Entity\Assessment;
use Drupal\joinup_solution\Entity\Solution;
use Drupal\og\OgGroupAudienceHelperInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Helpers for creating assessment entities in tests.
 */
trait AssessmentEntityCreationTrait {

  /**
   * Creates an assessment organisation.
   */
  protected function createAssessmentOrganisation(): AssessmentInterface {
    $organisation = Assessment::create([
      'type' => 'public_organisation',
      'name' => 'Test Organisation',
    ]);
    $this->markEntityForCleanup($organisation);
    return $organisation;
  }

  /**
   * Creates a binding requirement paragraph with all dependencies.
   *
   * @param \Drupal\joinup_solution\Entity\Solution|null $asset
   *   An optional SOLUTION_COLLECTOR solution to attach via the asset field.
   */
  protected function createParagraphBindingRequirement(
    ?Solution $asset = NULL,
  ): ParagraphInterface {
    $expression = Assessment::create([
      'type' => 'expression',
      'description' => ['value' => $this->randomString()],
      'documented_in' => ['uri' => 'https://interoperable-europe.ec.europa.eu/'],
    ]);
    $this->markEntityForCleanup($expression);

    $publicService = Assessment::create([
      'type' => 'public_service',
      'thematic_area' => [
        'entity' => $this->getAssessmentVocabularyHelper()
          ->getTermFromIdentifier('assess_public_service_area', 'general_public_services'),
      ],
    ]);
    $this->markEntityForCleanup($publicService);

    $participation = Assessment::create([
      'type' => 'participation',
      'participant_type' => [
        'entity' => $this->getAssessmentVocabularyHelper()
          ->getTermFromIdentifier('assess_participant_type', 'european_union_agency'),
      ],
    ]);
    $this->markEntityForCleanup($participation);

    $values = [
      'type' => 'binding_requirement',
      'description' => ['value' => $this->randomString()],
      'documented_in' => ['uri' => 'https://interoperable-europe.ec.europa.eu/'],
      'expression' => ['entity' => $expression],
      'affects' => ['entity' => $publicService],
      'has_participation' => ['entity' => $participation],
    ];
    if ($asset !== NULL) {
      $values['asset'] = ['entity' => $asset];
    }
    $bindingRequirement = Paragraph::create($values);
    $this->markEntityForCleanup($bindingRequirement);

    return $bindingRequirement;
  }

  /**
   * Creates a solution node inside the given collection.
   *
   * @param string $groupUuid
   *   UUID of the collection node to use as OG group, e.g.
   *   AssessmentInterface::SOLUTION_COLLECTOR.
   */
  protected function createSolutionAsset(string $groupUuid): Solution {
    $collection = $this->container
      ->get('entity.repository')
      ->loadEntityByUuid('node', $groupUuid);
    $asset = Solution::create([
      'type' => 'solution',
      'title' => 'Test Asset ' . $this->randomMachineName(),
      OgGroupAudienceHelperInterface::DEFAULT_FIELD => [
        'target_id' => $collection->id(),
      ],
      'field_state' => 'published',
    ]);
    $this->markEntityForCleanup($asset);
    return $asset;
  }

  /**
   * Returns the assessment vocabulary helper service.
   */
  private function getAssessmentVocabularyHelper(): AssessmentVocabularyHelper {
    return $this->container->get(AssessmentVocabularyHelper::class);
  }

}
