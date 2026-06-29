<?php

declare(strict_types=1);

namespace Drupal\joinup_solution;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Security\Attribute\TrustedCallback;
use Drupal\Core\Session\AccountInterface;
use Drupal\joinup_solution\Entity\SolutionInterface;
use Drupal\joinup_solution\Form\JoinSolutionForm;

/**
 * Defines a service for #lazy_builder callbacks for the Collection module.
 */
readonly class SolutionLazyBuilders {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected FormBuilderInterface $formBuilder,
    protected AccountInterface $currentUser,
    protected Connection $database,
  ) {}

  /**
   * Lazy builder callback; builds the Join Collection form.
   *
   * @param string $solution_id
   *   The solution ID.
   *
   * @return array
   *   A renderable array containing the comment form.
   */
  #[TrustedCallback]
  public function renderJoinSolutionForm(string $solution_id): array {
    $solution = $this->entityTypeManager->getStorage('node')->load($solution_id);
    return $this->formBuilder->getForm(JoinSolutionForm::class, $this->currentUser, $solution);
  }

  /**
   * Lazy builder callback; renders the assessment count for a solution.
   *
   * @param string $solution_id
   *   The solution node ID.
   *
   * @return array
   *   A renderable array.
   */
  #[TrustedCallback]
  public function renderAssessmentCount(string $solution_id): array {
    $solution = $this->entityTypeManager->getStorage('node')->load($solution_id);
    if (!$solution instanceof SolutionInterface) {
      return [];
    }
    $count = $this->assessmentCount($solution);
    $cache = [
      'tags' => Cache::mergeTags(
        $solution->getCacheTags(),
        ['assessment_list:report', 'paragraph_list:binding_requirement'],
      ),
    ];
    if ($count === 0) {
      return ['#cache' => $cache];
    }
    return [
      '#theme' => 'solution_assessment_count',
      '#count' => $count,
      '#cache' => $cache,
    ];
  }

  /**
   * Count of distinct published assessment reports referencing a solution.
   *
   * @param \Drupal\joinup_solution\Entity\SolutionInterface $solution
   *   The solution.
   *
   * @return int
   *   Number of distinct published assessment reports referencing the solution.
   */
  protected function assessmentCount(SolutionInterface $solution): int {
    $query = $this->database->select('assessment', 'a');
    $query->innerJoin('assessment_field_data', 'afd', 'a.id = afd.id AND afd.default_langcode = 1');
    $query->innerJoin('assessment__binding_requirement', 'abr', 'a.id = abr.entity_id AND abr.langcode = afd.langcode');
    $query->innerJoin('paragraphs_item_field_data', 'pifd', 'abr.binding_requirement_target_id = pifd.id AND pifd.default_langcode = 1');
    $query->innerJoin('paragraph__asset', 'pa', 'pifd.id = pa.entity_id AND pa.langcode = pifd.langcode');
    $query->condition('afd.status', 1)
      ->condition('a.type', 'report')
      ->condition('pa.asset_target_id', $solution->id())
      ->addExpression('COUNT(DISTINCT a.id)');
    return (int) $query->execute()->fetchField();
  }

}
