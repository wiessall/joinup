<?php

declare(strict_types=1);

namespace Drupal\joinup_distribution\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Utility\Error;
use Drupal\joinup_distribution\Entity\DistributionInterface;
use Drupal\joinup_distribution\Entity\DistributionsParentInterface;
use Drupal\joinup_release\Entity\ReleaseInterface;
use Drupal\joinup_solution\Entity\SolutionInterface;
use Drupal\og\OgGroupAudienceHelperInterface;

/**
 * Controller for distribution form.
 */
class JoinupDistributionController extends ControllerBase {

  /**
   * Controller for the base form.
   *
   * @param \Drupal\joinup_distribution\Entity\DistributionsParentInterface $node
   *   The release or solution.
   *
   * @return array
   *   Return the form array to be rendered.
   */
  public function createDistributionPage(DistributionsParentInterface $node): array {
    $form_state_additions = ['redirect' => $node->toUrl()];
    return $this->entityFormBuilder()->getForm($this->createDistribution($node), 'default', $form_state_additions);
  }

  /**
   * Handles access to the distribution add form.
   *
   * @param \Drupal\joinup_distribution\Entity\DistributionsParentInterface $node
   *   The release or solution.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result object.
   */
  public function createDistributionAccess(DistributionsParentInterface $node): AccessResultInterface {
    try {
      $distribution = $this->createDistribution($node);
    }
    catch (\Throwable $exception) {
      Error::logException($this->loggerFactory->get('joinup'), $exception);
      return AccessResult::forbidden();
    }
    return $this->entityTypeManager()->getAccessControlHandler('node')->createAccess('distribution', NULL, [
      'group' => $distribution->getSolution(),
    ], TRUE);
  }

  /**
   * Creates a new distribution entity.
   *
   * @param \Drupal\joinup_distribution\Entity\DistributionsParentInterface $parent
   *   The parent of the distribution.
   *
   * @return \Drupal\joinup_distribution\Entity\DistributionInterface
   *   The unsaved distribution entity.
   */
  protected function createDistribution(DistributionsParentInterface $parent): DistributionInterface {
    $release = $parent instanceof ReleaseInterface ? $parent : NULL;
    $solution = $parent instanceof SolutionInterface ? $parent : $release?->getSolution();
    $distribution = $this->entityTypeManager()->getStorage('node')->create([
      'type' => 'distribution',
      'release' => $release,
      OgGroupAudienceHelperInterface::DEFAULT_FIELD => $solution,
    ]);
    assert($distribution instanceof DistributionInterface);
    return $distribution;
  }

}
