<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Controller;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_group\JoinupGroupManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Route controller for the unpublished content page.
 */
class UnpublishedContentPageController extends ControllerBase {

  public function __construct(
    #[Autowire(service: 'joinup_group.group_manager')]
    protected readonly JoinupGroupManagerInterface $group_manager,
  ) {}

  /**
   * Renders a placeholder page for group unpublished content.
   *
   * @return array
   *   The render array.
   */
  public function unpublishedContent(): array {
    return [
      '#type' => 'markup',
      '#markup' => $this->t('Below you can find the content that is currently unpublished. Note that all content without the state field will be in the "Other unpublished content" section.'),
    ];
  }

  /**
   * Access check for the unpublished content page.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface $node
   *   The group entity from the URL.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result object.
   */
  public function access(GroupInterface $node): AccessResultInterface {
    return $this->group_manager->access($node, 'view any unpublished content', 'view any unpublished content');
  }

}
