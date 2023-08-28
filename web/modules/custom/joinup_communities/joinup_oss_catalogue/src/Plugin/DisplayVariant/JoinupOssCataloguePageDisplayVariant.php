<?php

declare(strict_types = 1);

namespace Drupal\joinup_oss_catalogue\Plugin\DisplayVariant;

use Drupal\Core\Display\PageVariantInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\block\BlockRepositoryInterface;
use Drupal\block\Plugin\DisplayVariant\BlockPageVariant;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Page display variant for OSS catalogue.
 *
 * @PageDisplayVariant(
 *   id = "joinup_oss_catalogue_page",
 *   admin_label = @Translation("OSS catalogue page"),
 * )
 */
class JoinupOssCataloguePageDisplayVariant extends BlockPageVariant {

  /**
   * Constructs a new JoinupOssCataloguePageDisplayVariant instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $pluginId
   *   The plugin ID for the plugin instance.
   * @param mixed $pluginDefinition
   *   The plugin implementation definition.
   * @param \Drupal\block\BlockRepositoryInterface $blockRepository
   *   The block repository.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    BlockRepositoryInterface $blockRepository,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct(
      $configuration,
      $pluginId,
      $pluginDefinition,
      $blockRepository,
      $entityTypeManager->getViewBuilder('block'),
      $entityTypeManager->getDefinition('block')->getListCacheTags()
    );
  }

  /**
   * Creates an instance of JoinupOssCataloguePageDisplayVariant plugin.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container to pull out services used in the plugin.
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $pluginId
   *   The plugin ID for the plugin instance.
   * @param mixed $pluginDefinition
   *   The plugin implementation definition.
   *
   * @return static
   *   Returns an instance of this plugin.
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $pluginId,
    $pluginDefinition
  ) {
    return new static(
      $configuration,
      $pluginId,
      $pluginDefinition,
      $container->get('block.repository'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'group' => NULL,
    ] + parent::defaultConfiguration();
  }

  /**
   * Sets the main content for the page being rendered.
   *
   * @param array $mainContent
   *   The render array representing the main content.
   *
   * @return $this
   *   The current object.
   */
  public function setMainContent(array $mainContent): PageVariantInterface {
    $pageStorage = $this->entityTypeManager->getStorage('page');
    $page = $pageStorage->load('oss_solutions');
    if (!$page) {
      return $this;
    }

    $viewBuilder = $this->entityTypeManager->getViewBuilder('page_variant');
    // Variants are sorted already.
    foreach ($page->getVariants() as $variant) {
      $this->mainContent = $viewBuilder->view($variant);
      break;
    }

    return $this;
  }

}
