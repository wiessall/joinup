<?php

declare(strict_types=1);

namespace Drupal\joinup_distribution\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\joinup_distribution\Entity\DistributionInterface;
use Drupal\joinup_release\Entity\ReleaseInterface;
use Drupal\joinup_solution\Entity\SolutionInterface;
use Drupal\node\NodeInterface;
use Drupal\og\OgGroupAudienceHelperInterface;
use Drupal\rdf_entity\RdfInterface;

/**
 * Controller for asset distribution forms.
 *
 * Handles the form to perform actions when it is called by a route that
 * includes an rdf_entity id.
 */
class JoinupDistributionController extends ControllerBase {

  /**
   * The solution parent.
   */
  protected SolutionInterface|null $solution = NULL;

  /**
   * Controller for the base form.
   *
   * We need to override the functionality of the create form for pages
   * that include the rdf_entity id in the url so that the asset release or the
   * solution refers to this asset distribution.
   *
   * @param \Drupal\rdf_entity\RdfInterface $rdf_entity
   *   The asset release or solution rdf entity.
   *
   * @return array
   *   Return the form array to be rendered.
   */
  public function add(RdfInterface $rdf_entity): array {
    $distribution = $this->createNewAssetDistribution($rdf_entity);
    $form_state_additions = ['redirect' => $rdf_entity->toUrl()];
    return $this->entityFormBuilder()->getForm($distribution, 'default', $form_state_additions);
  }

  /**
   * Controller for the base form.
   *
   * We need to override the functionality of the create form for pages
   * that include the rdf_entity id in the url so that the asset release or the
   * solution refers to this asset distribution.
   *
   * @todo This should be merged with the default add method in ISAICP-8572.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The asset release or solution rdf entity.
   *
   * @return array
   *   Return the form array to be rendered.
   */
  public function addOnNode(NodeInterface $node): array {
    $distribution = $this->createNewAssetDistribution($node);
    $form_state_additions = ['redirect' => $node->toUrl()];
    return $this->entityFormBuilder()->getForm($distribution, 'default', $form_state_additions);
  }

  /**
   * Handles access to the distribution add form.
   *
   * @param \Drupal\rdf_entity\RdfInterface $rdf_entity
   *   The asset release or solution RDF entity for which the distribution
   *   is created.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result object.
   */
  public function createAssetDistributionAccess(RdfInterface $rdf_entity): AccessResultInterface {
    // Create a new distribution entity in order to check permissions on it.
    $distribution = $this->createNewAssetDistribution($rdf_entity);

    // If the distribution entity isn't created correctly, forbid access to the
    // page.
    if (!$distribution) {
      return AccessResult::forbidden();
    }

    return $this->entityTypeManager()->getAccessControlHandler('node')->createAccess('distribution', NULL, [
      'group' => $this->getRelatedSolution($rdf_entity),
    ], TRUE);
  }

  /**
   * Handles access to the distribution add form.
   *
   * @todo This should be merged with the default access method in ISAICP-8572.
   *
   * @param \Drupal\joinup_release\Entity\ReleaseInterface $node
   *   The asset release or solution RDF entity for which the distribution
   *   is created.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result object.
   */
  public function createAssetDistributionOnNodeGroupAccess(ReleaseInterface $node): AccessResultInterface {
    // Create a new distribution entity in order to check permissions on it.
    $distribution = $this->createNewAssetDistribution($node);

    // If the distribution entity isn't created correctly, forbid access to the
    // page.
    if (!$distribution) {
      return AccessResult::forbidden();
    }

    return $this->entityTypeManager()->getAccessControlHandler('node')->createAccess('distribution', NULL, [
      'group' => $this->getRelatedSolution($node),
    ], TRUE);
  }

  /**
   * Creates a new distribution entity.
   *
   * @param \Drupal\joinup_release\Entity\ReleaseInterface|\Drupal\solution\Entity\SolutionInterface $parent
   *   The parent of the distribution.
   *
   * @return \Drupal\joinup_distribution\Entity\DistributionInterface|null
   *   The unsaved distribution entity or NULL if the parent is neither a
   *   solution nor a release.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function createNewAssetDistribution(ReleaseInterface|SolutionInterface $parent): ?DistributionInterface {
    // A solution is needed to create a distribution. If the $parent mparameter
    // is neither a solution nor a release, the variable will be empty.
    if (empty($this->getRelatedSolution($parent))) {
      return NULL;
    }

    return $this->entityTypeManager()->getStorage('node')->create([
      'type' => 'distribution',
      'release' => $parent instanceof ReleaseInterface ? $parent->id() : NULL,
      OgGroupAudienceHelperInterface::DEFAULT_FIELD => $this->solution,
    ]);
  }

  /**
   * Returns the solution entity from the rdf entity parameter.
   *
   * @todo Parameter should change to NodeInterface in ISAICP-8572.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The rdf entity parameter.
   *
   * @return \Drupal\rdf_entity\RdfInterface|null
   *   The solution of the release, if the entity is a release, or the solution
   *   itself if the entity is a solution. Returns NULL if the entity is neither
   *   a release nor a solution.
   */
  protected function getRelatedSolution(EntityInterface $entity): ?SolutionInterface {
    if ($this->solution) {
      return $this->solution;
    }

    if ($entity->bundle() === 'solution') {
      $this->solution = $entity;
    }

    if ($entity->bundle() === 'release') {
      $this->solution = $entity->og_audience->entity;
    }

    return $this->solution;
  }

}
