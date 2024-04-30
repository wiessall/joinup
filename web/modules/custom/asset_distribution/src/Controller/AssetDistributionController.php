<?php

declare(strict_types=1);

namespace Drupal\asset_distribution\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\og\OgGroupAudienceHelperInterface;
use Drupal\rdf_entity\RdfInterface;
use Drupal\solution\Entity\SolutionInterface;

/**
 * Controller for asset distribution forms.
 *
 * Handles the form to perform actions when it is called by a route that
 * includes an rdf_entity id.
 */
class AssetDistributionController extends ControllerBase {

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

    return $this->entityTypeManager()->getAccessControlHandler('rdf_entity')->createAccess('asset_distribution', NULL, [
      'group' => $this->getRelatedSolution($rdf_entity),
    ], TRUE);
  }

  /**
   * Creates a new asset_distribution entity.
   *
   * @param \Drupal\rdf_entity\RdfInterface $rdf_entity
   *   The RDF entity that the distribution is associated with. Can be either an
   *   'asset_release' or a 'solution'.
   *
   * @return \Drupal\rdf_entity\RdfInterface|null
   *   The unsaved asset_distribution entity or NULL if the RDF entity parameter
   *   is neither a solution nor a release.
   */
  protected function createNewAssetDistribution(RdfInterface $rdf_entity): ?RdfInterface {
    // A solution is needed to create a distribution. If the rdf entity
    // parameter is neither a solution or a release, the variable will be empty.
    if (empty($this->getRelatedSolution($rdf_entity))) {
      return NULL;
    }

    return $this->entityTypeManager()->getStorage('rdf_entity')->create([
      'rid' => 'asset_distribution',
      'parent' => $rdf_entity->id(),
      OgGroupAudienceHelperInterface::DEFAULT_FIELD => $this->solution,
    ]);
  }

  /**
   * Returns the solution entity from the rdf entity parameter.
   *
   * @param \Drupal\rdf_entity\RdfInterface $rdf_entity
   *   The rdf entity parameter.
   *
   * @return \Drupal\rdf_entity\RdfInterface|null
   *   The solution of the release, if the entity is a release, or the solution
   *   itself if the entity is a solution. Returns NULL if the entity is neither
   *   a release nor a solution.
   */
  protected function getRelatedSolution(RdfInterface $rdf_entity): ?SolutionInterface {
    if ($this->solution) {
      return $this->solution;
    }

    if ($rdf_entity->bundle() === 'solution') {
      $this->solution = $rdf_entity;
    }

    if ($rdf_entity->bundle() === 'asset_release') {
      $this->solution = $rdf_entity->field_isr_is_version_of->entity;
    }

    return $this->solution;
  }

}
