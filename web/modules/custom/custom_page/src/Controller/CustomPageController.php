<?php

declare(strict_types = 1);

namespace Drupal\custom_page\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;
use Drupal\og_menu\OgMenuInstanceInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for custom pages.
 */
class CustomPageController {

  use StringTranslationTrait;

  /**
   * Altered title callback for the navigation menu edit form.
   *
   * @param \Drupal\og_menu\OgMenuInstanceInterface $ogmenu_instance
   *   The OG Menu instance that is being edited.
   *
   * @return array
   *   The title as a render array.
   *
   * @see \Drupal\custom_page\Routing\RouteSubscriber::alterRoutes()
   */
  public function editFormTitle(OgMenuInstanceInterface $ogmenu_instance) {
    // Provide a custom title for the OG Menu instance edit form. The default
    // menu is suitable for webmasters, but we need a simpler title since this
    // form is exposed to regular visitors.
    $group = $ogmenu_instance->og_audience->entity;
    return [
      '#markup' => $this->t('Edit navigation menu of the %group @type', [
        '%group' => $ogmenu_instance->label(),
        '@type' => $group->bundle(),
      ]),
      '#allowed_tags' => Xss::getHtmlTagList(),
    ];
  }

  /**
   * Serves a file of the custom page attachments in the given delta position.
   *
   * The controller throws a not found controller if the file is not found.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The custom page entity.
   * @param int $delta
   *   The delta that corresponds to the file's position.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The file served as a response.
   */
  public function fileDownload(NodeInterface $node, int $delta): Response {
    if ($node->get('field_attachment')->get($delta) && $file = $node->get('field_attachment')->get($delta)->entity) {
      $response = new BinaryFileResponse($file->getFileUri());
      $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT);
      return $response;
    }
    throw new NotFoundHttpException();
  }


}
