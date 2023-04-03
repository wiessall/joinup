<?php

declare(strict_types = 1);

namespace Drupal\ventuno_helper\Render\MainContent;

use Drupal\Core\Controller\TitleResolverInterface;
use Drupal\Core\Render\MainContent\MainContentRendererInterface;
use Drupal\Core\Render\MainContent\ModalRenderer;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Modal dialog renderer that uses Bootstrap modals when using Ventuno theme.
 */
class VentunoModalRenderer extends ModalRenderer {

  /**
   * The 'Boostrap 4 Modal' modal content renderer.
   *
   * @var \Drupal\Core\Render\MainContent\MainContentRendererInterface
   */
  protected MainContentRendererInterface $bootstrap4ModalRenderer;

  /**
   * The theme manager service.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface
   */
  protected ThemeManagerInterface $themeManager;

  /**
   * Constructs a new dialog renderer.
   *
   * @param \Drupal\Core\Controller\TitleResolverInterface $title_resolver
   *   The title resolver.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Render\MainContent\MainContentRendererInterface $bootstrap_4_modal_renderer
   *   The 'Boostrap 4 Modal' modal content renderer.
   * @param \Drupal\Core\Theme\ThemeManagerInterface $theme_manager
   *   The theme manager service.
   */
  public function __construct(TitleResolverInterface $title_resolver, RendererInterface $renderer, MainContentRendererInterface $bootstrap_4_modal_renderer, ThemeManagerInterface $theme_manager) {
    parent::__construct($title_resolver, $renderer);
    $this->bootstrap4ModalRenderer = $bootstrap_4_modal_renderer;
    $this->themeManager = $theme_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function renderResponse(array $main_content, Request $request, RouteMatchInterface $route_match): Response {
    if (in_array($this->themeManager->getActiveTheme()->getName(), [
      'ventuno',
      'iop',
    ])) {
      $request->request->set('dialogOptions', [
        'dialogClasses' => ['modal-dialog-centered', 'modal-xl'],
        'dialogShowHeader' => TRUE,
        'dialogShowHeaderTitle' => TRUE,
      ]);
      /** @var \Drupal\Core\Ajax\AjaxResponse $response */
      $response = $this->bootstrap4ModalRenderer->renderResponse($main_content, $request, $route_match);
      return $response->addAttachments(['library' => ['ventuno/modals']]);
    }
    return parent::renderResponse($main_content, $request, $route_match);
  }

}
