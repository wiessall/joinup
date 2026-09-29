<?php

declare(strict_types=1);

namespace Drupal\atio\Hook;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\layout_builder\Entity\LayoutEntityDisplayInterface;
use Drupal\node\NodeInterface;

/**
 * Provides the "View switch" pseudo-field on atio_innovation.
 *
 * Three links: the canonical page (ATIO fields and claims), the ATIO fields
 * only, and the source claims only. Placed in Manage display / Layout Builder
 * like any field. The active link follows the view mode being rendered, which
 * is already part of the render cache keys, so nothing here varies by request.
 */
final class AtioViewSwitchHooks {

  use StringTranslationTrait;

  /**
   * Routes that show an innovation in another view mode as the page itself.
   */
  private const PAGE_ROUTES = ['entity.node.atio_fields', 'entity.node.claims'];

  public function __construct(
    private readonly RouteMatchInterface $routeMatch,
  ) {}

  /**
   * Implements hook_preprocess_HOOK() for node templates.
   *
   * Core sets $page only for the 'full' view mode on the canonical route, and
   * node.html.twig prints the title again when $page is false. On
   * /atio-fields and /claims the node *is* the page, so say so.
   */
  #[Hook('preprocess_node')]
  public function preprocessNode(array &$variables): void {
    $node = $variables['node'] ?? NULL;
    if (!$node instanceof NodeInterface || !in_array($this->routeMatch->getRouteName(), self::PAGE_ROUTES, TRUE)) {
      return;
    }
    $route_node = $this->routeMatch->getParameter('node');
    if ($route_node instanceof NodeInterface && (string) $route_node->id() === (string) $node->id()) {
      $variables['page'] = TRUE;
    }
  }

  /**
   * Implements hook_entity_extra_field_info().
   */
  #[Hook('entity_extra_field_info')]
  public function entityExtraFieldInfo(): array {
    $extra['node']['atio_innovation']['display']['atio_view_switch'] = [
      'label' => $this->t('View switch'),
      'description' => $this->t('Links to show the ATIO fields, the source claims, or both.'),
      'weight' => 0,
      'visible' => FALSE,
    ];
    return $extra;
  }

  /**
   * Implements hook_node_view().
   */
  #[Hook('node_view')]
  public function nodeView(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, string $view_mode): void {
    if ($entity->bundle() !== 'atio_innovation' || $entity->isNew()) {
      return;
    }
    // Layout Builder places extra fields itself and drops them if unplaced;
    // a plain display has to ask whether the field is shown.
    $uses_layout_builder = $display instanceof LayoutEntityDisplayInterface && $display->isLayoutBuilderEnabled();
    if (!$uses_layout_builder && !$display->getComponent('atio_view_switch')) {
      return;
    }

    $links = [
      'both' => [$this->t('ATIO fields and claims'), 'entity.node.canonical', ['full', 'default']],
      'atio' => [$this->t('ATIO fields'), 'entity.node.atio_fields', ['atio_fields']],
      'claims' => [$this->t('Source claims'), 'entity.node.claims', ['claims']],
    ];
    $items = [];
    foreach ($links as $key => [$title, $route, $modes]) {
      $active = in_array($view_mode, $modes, TRUE);
      $items[$key] = [
        '#type' => 'link',
        '#title' => $title,
        '#url' => Url::fromRoute($route, ['node' => $entity->id()]),
        '#attributes' => [
          'class' => ['btn', 'btn-sm', $active ? 'btn-primary' : 'btn-outline-primary'],
        ] + ($active ? ['aria-current' => 'page'] : []),
      ];
    }
    $build['atio_view_switch'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['atio-view-switch', 'btn-group', 'mt-3'],
        'role' => 'group',
        'aria-label' => $this->t('Show'),
      ],
    ] + $items;
  }

}
