<?php

declare(strict_types = 1);

namespace Drupal\eif\Controller;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\meta_entity\MetaEntityRepositoryInterface;
use Drupal\og\OgAccessInterface;
use Drupal\solution\Entity\SolutionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller class for the solution EIF settings.
 */
class EifPerspectiveController extends ControllerBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The meta entity repository service.
   *
   * @var \Drupal\meta_entity\MetaEntityRepositoryInterface
   */
  protected $metaEntityRepository;

  /**
   * The OG access service.
   *
   * @var \Drupal\og\OgAccessInterface
   */
  protected $ogAccess;

  /**
   * Constructs a new controller instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\meta_entity\MetaEntityRepositoryInterface $meta_entity_repository
   *   The meta entity repository service.
   * @param \Drupal\og\OgAccessInterface $og_access
   *   The OG access service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, MetaEntityRepositoryInterface $meta_entity_repository, OgAccessInterface $og_access) {
    $this->entityTypeManager = $entity_type_manager;
    $this->metaEntityRepository = $meta_entity_repository;
    $this->ogAccess = $og_access;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('rdf_meta_entity.repository'),
      $container->get('og.access')
    );
  }

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
    return $this->t('@solution EIF settings', [
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
    $meta_entity = $this->metaEntityRepository->getMetaEntityForEntity($rdf_entity, 'eif_perspective');
    if (empty($meta_entity)) {
      $meta_entity = $this->entityTypeManager->getStorage('rdf_meta_entity')->create([
        'type' => 'eif_perspective',
        'target' => $rdf_entity,
      ]);
    }

    $form_state_additions = [
      'redirect' => Url::fromRoute('entity.rdf_entity.canonical', [
        'rdf_entity' => $rdf_entity->id(),
      ]),
    ];
    return $this->entityFormBuilder()->getForm($meta_entity, 'add', $form_state_additions);
  }

  /**
   * Provides an access controller for the 'eif.eif_perspective_form' route.
   *
   * @param \Drupal\solution\Entity\SolutionInterface $rdf_entity
   *   The solution.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(SolutionInterface $rdf_entity): AccessResultInterface {
    if ($meta_entity = $this->metaEntityRepository->getMetaEntityForEntity($rdf_entity, 'eif_perspective')) {
      $access_result = $meta_entity->access('update', NULL, TRUE);
    }
    else {
      $access_result = $this->entityTypeManager->getAccessControlHandler('rdf_meta_entity')->createAccess('eif_perspective', NULL, [], TRUE);
    }

    if ($access_result->isAllowed()) {
      return $access_result;
    }
    return $access_result->orIf($this->ogAccess->userAccess($rdf_entity, 'update eif_perspective rdf-meta-entity'));
  }

}
