<?php

declare(strict_types=1);

namespace Drupal\assessments\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the add page for assessments with custom access filtering.
 */
final class AssessmentAddPageController implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * Constructs a new AssessmentAddPageController.
   */
  public function __construct(
    private readonly EntityTypeBundleInfoInterface $bundleInfo,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountInterface $currentUser,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('entity_type.bundle.info'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
    );
  }

  /**
   * Displays add links for available bundles.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return array
   *   A render array.
   */
  public function addPage(string $entity_type_id): array {
    $entityType = $this->entityTypeManager->getDefinition($entity_type_id);
    $bundles = $this->bundleInfo->getBundleInfo($entity_type_id);
    $bundleEntityTypeId = $entityType->getBundleEntityType();

    $build = [
      '#theme' => 'entity_add_list',
      '#bundles' => [],
      '#cache' => [
        'tags' => ['assessment_type_list'],
        'contexts' => ['user.permissions'],
      ],
    ];

    $formRouteName = 'entity.' . $entity_type_id . '.add_form';

    foreach ($bundles as $bundleName => $bundleInfo) {
      if (!$this->currentUser->hasPermission("create assessment $bundleName")) {
        continue;
      }
      $build['#bundles'][$bundleName] = [
        'label' => $bundleInfo['label'],
        'description' => $bundleInfo['description'] ?? '',
        'add_link' => Link::createFromRoute(
          $bundleInfo['label'],
          $formRouteName,
          [$bundleEntityTypeId => $bundleName]
        ),
      ];
    }

    return $build;
  }

}
