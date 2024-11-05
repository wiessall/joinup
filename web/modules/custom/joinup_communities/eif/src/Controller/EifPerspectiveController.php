<?php

declare(strict_types=1);

namespace Drupal\eif\Controller;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\joinup_solution\Entity\SolutionInterface;
use Drupal\meta_entity\Entity\MetaEntity;

/**
 * Controller class for the solution EIF perspective.
 */
class EifPerspectiveController extends ControllerBase {

  /**
   * Provides a controller for the 'eif.eif_perspective_form' route title.
   *
   * @param \Drupal\joinup_solution\Entity\SolutionInterface $node
   *   The solution.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   The content as a render array.
   */
  public function title(SolutionInterface $node): MarkupInterface {
    return $this->t('@solution EIF perspective', [
      '@solution' => $node->label(),
    ]);
  }

  /**
   * Provides a controller for the 'eif.eif_perspective' route title.
   *
   * @param \Drupal\joinup_solution\Entity\SolutionInterface $node
   *   The solution.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   The content as a render array.
   */
  public function getTitle(SolutionInterface $node): MarkupInterface {
    return $this->t('EIF Perspective attributes of @solution', [
      '@solution' => $node->label(),
    ]);
  }

  /**
   * Provides a controller for the 'eif.eif_perspective_form' route.
   *
   * @param \Drupal\joinup_solution\Entity\SolutionInterface $node
   *   The solution.
   *
   * @return array
   *   The content as a render array.
   */
  public function settings(SolutionInterface $node): array {
    $meta_entity = MetaEntity::loadOrCreate('eif_perspective', $node);
    return $this->entityFormBuilder()->getForm($meta_entity, 'default', [
      'redirect' => Url::fromRoute('entity.node.canonical', [
        'node' => $node->id(),
      ]),
    ]);
  }

  /**
   * Provides an access callback for the 'eif.eif_perspective_form' route.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account being checked.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function editAccess(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIfHasPermissions($account, ['update eif_perspective meta-entity']);
  }

  /**
   * Provides an access callback to view the EIF perspective.
   *
   * @param \Drupal\joinup_solution\Entity\SolutionInterface $node
   *   The solution.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function viewAccess(SolutionInterface $node): AccessResultInterface {
    if (empty($node->eif_perspective->entity) || !$node->access('view')) {
      return AccessResult::forbidden();
    }

    return $this->entityTypeManager()
      ->getAccessControlHandler('meta_entity')
      ->access($node->eif_perspective->entity, 'view', NULL, TRUE);
  }

}
