<?php

declare(strict_types=1);

namespace Drupal\solution;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\rdf_entity\RdfInterface;
use Drupal\sparql_entity_storage\SparqlEntityStorageGraphHandlerInterface;

/**
 * Provides a default class for the 'solution.title_duplicate_helper' service.
 */
class SolutionTitleDuplicateHelper implements SolutionTitleDuplicateHelperInterface {

  /**
   * Constructs a new service instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\sparql_entity_storage\SparqlEntityStorageGraphHandlerInterface $graphHandler
   *   The SPARQL graph handler service.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected SparqlEntityStorageGraphHandlerInterface $graphHandler,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function titleIsUnique(RdfInterface $solution): bool {
    return empty($this->getSameTitleSolutionIds($solution));
  }

  /**
   * {@inheritdoc}
   */
  public function titleIsUniqueWithinAffiliation(RdfInterface $solution): ?bool {
    if (!$solution_ids = $this->getSameTitleSolutionIds($solution)) {
      return TRUE;
    }
    $storage = $this->entityTypeManager->getStorage('node');

    // Get the solution's affiliation.
    if (!$collection_ids = $this->getCollectionIdsFromSolution($solution)) {
      // Normally a solution cannot exist without an affiliation but there are
      // certain exceptions, when the parent collection is not yet configured.
      // Such a case is the data federation, where the imported solutions are
      // stored in a 'nonofficial' graph and, temporary, orphan solutions are
      // allowed. See the 'joinup_federation' module for more details. As an
      // effect we cannot determine if the solution title is unique as we don't
      // know yet the solution affiliation.
      // @see \Drupal\solution\SolutionAffiliationFieldItemList
      return NULL;
    }

    return !array_filter($storage->loadMultiple($solution_ids), function (NodeInterface $solution) use ($collection_ids): bool {
      return (bool) array_intersect($this->getCollectionIdsFromSolution($solution), $collection_ids);
    });
  }

  /**
   * Returns a a list of solution IDs with the same title as the given solution.
   *
   * @param \Drupal\rdf_entity\RdfInterface $solution
   *   The solution to be checked.
   *
   * @return string[]
   *   A list of solution IDs.
   *
   * @throws \InvalidArgumentException
   *   When the passed entity is not a solution.
   */
  protected function getSameTitleSolutionIds(RdfInterface $solution): array {
    if ($solution->bundle() !== 'solution') {
      throw new \InvalidArgumentException('Title uniqueness within a collection can only be checked for solutions.');
    }

    if (empty($solution->label())) {
      return [];
    }

    $storage = $this->entityTypeManager->getStorage('node');

    // The relationship between a solution and its collections is not on the
    // child entity but on the parent. This means we cannot use a simple query.
    // Retrieve all solutions with the same title.
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('title', $solution->label())
      ->condition('node', 'solution')
      ->condition('status', 1);
    if ($solution->id()) {
      $query->condition('id', $solution->id(), '<>');
    }

    return array_values($query->execute());
  }

  /**
   * Returns the list of collection IDs where the give solution is affiliated.
   *
   * @param \Drupal\node\NodeInterface $solution
   *   The solution.
   *
   * @return string[]
   *   A list of collection IDs.
   */
  protected function getCollectionIdsFromSolution(NodeInterface $solution): array {
    return array_map(function (array $reference): string {
      return $reference['target_id'];
    }, $solution->get('collection')->getValue());
  }

}
