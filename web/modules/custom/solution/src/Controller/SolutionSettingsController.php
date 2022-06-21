<?php

declare(strict_types = 1);

namespace Drupal\solution\Controller;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\meta_entity\MetaEntityRepositoryInterface;
use Drupal\solution\Entity\SolutionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller class for solution settings.
 */
class SolutionSettingsController extends ControllerBase {

  /**
   * The meta entity repository service.
   *
   * @var \Drupal\meta_entity\MetaEntityRepositoryInterface
   */
  protected MetaEntityRepositoryInterface $metaEntityRepository;

  /**
   * Constructs a new controller instance.
   *
   * @param \Drupal\meta_entity\MetaEntityRepositoryInterface $meta_entity_repository
   *   The meta entity repository service.
   */
  public function __construct(MetaEntityRepositoryInterface $meta_entity_repository) {
    $this->metaEntityRepository = $meta_entity_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('meta_entity.repository')
    );
  }

  /**
   * Provides a controller for the 'solution.settings_form' route title.
   *
   * @param \Drupal\solution\Entity\SolutionInterface $rdf_entity
   *   The solution.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   The content as a render array.
   */
  public function title(SolutionInterface $rdf_entity): MarkupInterface {
    return $this->t('@solution solution settings', [
      '@solution' => $rdf_entity->label(),
    ]);
  }

  /**
   * Provides a controller for the 'solution.settings_form' route.
   *
   * @param \Drupal\solution\Entity\SolutionInterface $rdf_entity
   *   The solution.
   *
   * @return array
   *   The content as a render array.
   */
  public function settings(SolutionInterface $rdf_entity): array {
    $meta_entity = $this->metaEntityRepository->getMetaEntityForEntity($rdf_entity, 'solution_settings');
    $form_state_additions = [
      'redirect' => Url::fromRoute('entity.rdf_entity.canonical', [
        'rdf_entity' => $rdf_entity->id(),
      ]),
    ];
    return $this->entityFormBuilder()->getForm($meta_entity, 'default', $form_state_additions);
  }

  /**
   * Provides an access controller for the 'solution.settings_form' route.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account for which to check access.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIf($account->hasPermission('update solution_settings meta-entity'));
  }

}
