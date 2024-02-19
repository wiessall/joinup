<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Controller;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\RendererInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_group\JoinupGroupManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Route controller for the unpublished content page.
 */
class UnpublishedContentPageController extends ControllerBase {

  /**
   * Constructs a new UnpublishedContentPageController object.
   *
   * @param \Drupal\Core\Block\BlockManagerInterface $block_plugin_manager
   *   The block plugin manager service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\joinup_group\JoinupGroupManagerInterface $group_manager
   *   The Joinup group manager service.
   */
  public function __construct(protected BlockManagerInterface $block_plugin_manager, protected RendererInterface $renderer, protected JoinupGroupManagerInterface $group_manager) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.block'),
      $container->get('renderer'),
      $container->get('joinup_group.group_manager')
    );
  }

  /**
   * Renders a placeholder page for group unpublished content.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface $rdf_entity
   *   The group entity from the URL.
   *
   * @return array
   *   The render array.
   */
  public function unpublishedContent(GroupInterface $rdf_entity): array {
    return [
      '#type' => 'markup',
      '#markup' => $this->t('Below you can find the content that is currently unpublished. Note that all content without the state field will be in the "Other unpublished content" section.'),
    ];
  }

  /**
   * Access check for the unpublished content page.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface $rdf_entity
   *   The group entity from the URL.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result object.
   */
  public function access(GroupInterface $rdf_entity): AccessResultInterface {
    return $this->group_manager->access($rdf_entity, 'view any unpublished content', 'view any unpublished content');
  }

}
