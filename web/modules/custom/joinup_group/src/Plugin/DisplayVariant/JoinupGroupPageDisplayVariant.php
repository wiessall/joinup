<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Plugin\DisplayVariant;

use Drupal\Core\Display\Attribute\PageDisplayVariant;
use Drupal\Core\Display\PageVariantInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityViewBuilderInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\block\BlockRepositoryInterface;
use Drupal\block\Plugin\DisplayVariant\BlockPageVariant;
use Drupal\custom_page\Entity\CustomPageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Page display variant allowing to configure a custom landing page for groups.
 */
#[PageDisplayVariant(
  id: "joinup_group_canonical_page",
  admin_label: new TranslatableMarkup("Group landing page"),
)]
class JoinupGroupPageDisplayVariant extends BlockPageVariant {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    BlockRepositoryInterface $block_repository,
    EntityViewBuilderInterface $block_view_builder,
    array $block_list_cache_tags,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $block_repository, $block_view_builder, $block_list_cache_tags);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('block.repository'),
      $container->get('entity_type.manager')->getViewBuilder('block'),
      $container->get('entity_type.manager')->getDefinition('block')->getListCacheTags(),
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
   * {@inheritdoc}
   */
  public function setMainContent(array $main_content): PageVariantInterface {
    if ($landing_page = $this->getCustomLandingPage()) {
      $node_builder = $this->entityTypeManager->getViewBuilder('node');
      $main_content = $node_builder->view($landing_page);
    }

    // Tag with the meta-entity bundle list tag, so that, when the group
    // settings is added or changed, we build again the page's render array.
    $main_content['#cache']['tags'][] = 'meta_entity_list:' . $this->getConfiguration()['group']->bundle() . '_settings';

    $this->mainContent = $main_content;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setTitle($title): PageVariantInterface {
    if ($landing_page = $this->getCustomLandingPage()) {
      $title = $landing_page->isHiddenTitleEnabled() ? '' : $landing_page->label();
    }
    $this->title = $title;
    return $this;
  }

  /**
   * Returns a custom page to be used as landing page, if any.
   *
   * @return \Drupal\custom_page\Entity\CustomPageInterface|null
   *   The page used as group landing page, if any.
   */
  protected function getCustomLandingPage(): ?CustomPageInterface {
    /** @var \Drupal\joinup_group\Entity\GroupInterface $group */
    if ($group = $this->getConfiguration()['group']) {
      $landing_page = $group->getSetting('landing_page');
      return $landing_page->entity;
    }
    else {
      return NULL;
    }
  }

}
