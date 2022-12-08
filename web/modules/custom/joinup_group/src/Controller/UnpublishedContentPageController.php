<?php

declare(strict_types = 1);

namespace Drupal\joinup_group\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\RendererInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\rdf_entity\RdfInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Route controller for the unpublished content page.
 */
class UnpublishedContentPageController extends ControllerBase {

  /**
   * Constructs a new OgAdminMembersController object.
   *
   * @param \Drupal\Core\Block\BlockManagerInterface $block_plugin_manager
   *   The block plugin manager service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(public BlockManagerInterface $block_plugin_manager, public RendererInterface $renderer) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.block'),
      $container->get('renderer')
    );
  }

  /**
   * Renders the "unpublishedContent" view mode of a rdf entity.
   *
   * @param \Drupal\rdf_entity\RdfInterface $rdf_entity
   *   The rdf entity to be rendered.
   *
   * @return array
   *   The "unpublished" view mode render array.
   */
  public function unpublishedContent(RdfInterface $rdf_entity): array {
    $draft_block = $this->getBlock('entity_draft_state', $rdf_entity);
    $proposed_block = $this->getBlock('entity_proposed_state', $rdf_entity);

    return [
      '#theme' => 'group_unpublished_content_page',
      '#draft_block' => $draft_block,
      '#proposed_block' => $proposed_block,
    ];
  }

  /**
   * Get Block from plugin manager block.
   *
   * @param string $block_id
   *   Block Id.
   * @param \Drupal\rdf_entity\RdfInterface $rdf_entity
   *   Rdf Entity.
   *
   * @return array
   *   The block render array.
   */
  protected function getBlock(string $block_id, RdfInterface $rdf_entity): array {
    $plugin_block = $this->block_plugin_manager->createInstance($block_id, []);
    $plugin_block->setContextValue('og', $rdf_entity);
    $render = $plugin_block->build();
    // Add the cache tags/contexts.
    $this->renderer->addCacheableDependency($render, $plugin_block);
    return $render;
  }

  /**
   * Access check for the upublished content page.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface $rdf_entity
   *   The group for which the access to the members page is being determined.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result object.
   */
  public function access(GroupInterface $rdf_entity): AccessResultInterface {

    $roles = $this->currentUser()->getRoles();
    if (!empty(array_intersect(['administrator', 'moderator', 'authenticated'], $roles))) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();

  }

}
