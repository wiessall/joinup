<?php

declare(strict_types=1);

namespace Drupal\joinup_oss_catalogue\Plugin\OgGroupResolver;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\joinup_oss_catalogue\Entity\JoinupOssSolution;
use Drupal\joinup_oss_catalogue\OssCatalogueCollectionInterface;
use Drupal\og\Attribute\OgGroupResolver;
use Drupal\og\GroupTypeManagerInterface;
use Drupal\og\OgResolvedGroupCollectionInterface;
use Drupal\og\OgRouteGroupResolverBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Resolves the group for EU OSS catalogue search route.
 */
#[OgGroupResolver(
  id: 'eif_group',
  label: new TranslatableMarkup('joinup_oss_catalogue_routes'),
  description: new TranslatableMarkup('Routes related to EU OSS catalogue')
)]
class JoinupOssCatalogueGroupResolver extends OgRouteGroupResolverBase {

  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    RouteMatchInterface $route_match,
    GroupTypeManagerInterface $group_type_manager,
    EntityTypeManagerInterface $entity_type_manager,
    protected readonly EntityRepositoryInterface $entityRepository,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $route_match, $group_type_manager, $entity_type_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('og.group_type_manager'),
      $container->get('entity_type.manager'),
      $container->get('entity.repository'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(OgResolvedGroupCollectionInterface $collection): void {
    if (!$this->isOssContent()) {
      return;
    }

    /** @var \Drupal\collection\Entity\CollectionInterface $ossCatalogueGroup */
    $ossCatalogueGroup = $this->entityRepository
      ->loadEntityByUuid('node', OssCatalogueCollectionInterface::COLLECTION_UUID);
    if ($ossCatalogueGroup) {
      $collection->addGroup($ossCatalogueGroup, ['route']);
      $this->stopPropagation();
    }
  }

  /**
   * Checks if it is Oss content.
   *
   * @return bool
   *   True if it is Oss content.
   */
  protected function isOssContent(): bool {
    if ($this->routeMatch->getRouteName() === 'view.search_oss_catalogue.search') {
      return TRUE;
    }

    $entity = $this->getContentEntity();
    if ($entity instanceof JoinupOssSolution) {
      return TRUE;
    }

    return FALSE;
  }

}
