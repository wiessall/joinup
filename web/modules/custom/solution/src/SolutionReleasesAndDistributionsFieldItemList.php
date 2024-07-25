<?php

declare(strict_types=1);

namespace Drupal\solution;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Defines a field item list class for the 'releases_and_distributions' field.
 *
 * This computed field returns a list references to solution's releases and
 * standalone distributions. It's a read-only field.
 *
 * @todo This currently reuses the storage graph of the solution. This works
 *   only as intended for published solutions. In case the solution is not
 *   published, only the unpublished releases and distributions will be
 *   returned.
 *
 * @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-5736
 */
class SolutionReleasesAndDistributionsFieldItemList extends EntityReferenceFieldItemList {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue(): void {
    if ($this->getEntity()->id()) {
      foreach ($this->getReleasesAndDistributions() as $delta => $id) {
        $this->list[$delta] = $this->createItem($delta, ['target_id' => $id]);
      }
    }
  }

  /**
   * Returns a list of solution's releases and standalone distributions as IDs.
   *
   * @return string[]
   *   A list of solution's releases and standalone distributions as IDs.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown if the storage handler couldn't be loaded.
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown if the 'rdf_entity' entity type doesn't exist.
   */
  protected function getReleasesAndDistributions(): array {
    $entityTypeManager = \Drupal::entityTypeManager();
    /** @var \Drupal\sparql_entity_storage\SparqlEntityStorageInterface $storage */
    $storage = $entityTypeManager->getStorage('node');
    /** @var \Drupal\solution\Entity\SolutionInterface $solution */
    $solution = $this->getEntity();

    // Retrieve all releases and distributions for this solution.
    $ids = array_values($storage
      ->getQuery()
      ->accessCheck()
      ->condition('type', 'release')
      ->condition('og_audience', $solution->id())
      ->sort('created', 'DESC')
      ->execute());

    // Retrieve also, all standalone distributions for this solution.
    return $ids + \Drupal::getContainer()->get('joinup_distribution.parent_distribution_resolver')->getParentsDistributionIds($solution->id());
  }

}
