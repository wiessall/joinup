<?php

declare(strict_types=1);

namespace Drupal\eif\Controller;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\rdf_meta_entity\Entity\RdfMetaEntity;
use Drupal\solution\Entity\SolutionInterface;

/**
 * Controller class for the solution EIF perspective.
 */
class EifPerspectiveController extends ControllerBase {

  /**
   * Provides a controller for the 'eif.eif_perspective_form' route title.
   *
   * @param \Drupal\solution\Entity\SolutionInterface $rdf_entity
   *   The solution.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   The content as a render array.
   */
  public function title(SolutionInterface $rdf_entity): MarkupInterface {
    return $this->t('@solution EIF perspective', [
      '@solution' => $rdf_entity->label(),
    ]);
  }

  /**
   * Provides a controller for the 'eif.eif_perspective' route title.
   *
   * @param \Drupal\solution\Entity\SolutionInterface $rdf_entity
   *   The solution.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   The content as a render array.
   */
  public function getTitle(SolutionInterface $rdf_entity): MarkupInterface {
    return $this->t('EIF Perspective attributes of @solution', [
      '@solution' => $rdf_entity->label(),
    ]);
  }

  /**
   * Provides a controller for the 'eif.eif_perspective_form' route.
   *
   * @param \Drupal\solution\Entity\SolutionInterface $rdf_entity
   *   The solution.
   *
   * @return array
   *   The content as a render array.
   */
  public function settings(SolutionInterface $rdf_entity): array {
    $meta_entity = RdfMetaEntity::loadOrCreate('eif_perspective', $rdf_entity);
    return $this->entityFormBuilder()->getForm($meta_entity, 'default', [
      'redirect' => Url::fromRoute('entity.rdf_entity.canonical', [
        'rdf_entity' => $rdf_entity->id(),
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
    return AccessResult::allowedIfHasPermissions($account, ['update eif_perspective rdf-meta-entity']);
  }

  /**
   * Provides an access callback to view the EIF perspective.
   *
   * @param \Drupal\solution\Entity\SolutionInterface $rdf_entity
   *   The solution.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function viewAccess(SolutionInterface $rdf_entity): AccessResultInterface {
    if (empty($rdf_entity->eif_perspective->entity) || !$rdf_entity->access('view')) {
      return AccessResult::forbidden();
    }

    return $this->entityTypeManager()
      ->getAccessControlHandler('rdf_meta_entity')
      ->access($rdf_entity->eif_perspective->entity, 'view', NULL, TRUE);
  }

}
