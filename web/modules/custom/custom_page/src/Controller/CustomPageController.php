<?php

declare(strict_types=1);

namespace Drupal\custom_page\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Cache\CacheableRedirectResponse;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;
use Drupal\og_menu\OgMenuInstanceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for custom pages.
 */
class CustomPageController implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The file URL generator.
   */
  protected FileUrlGeneratorInterface $urlGenerator;

  /**
   * Constructs a new CustomPageController.
   *
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $url_generator
   *   The file URL generator.
   */
  public function __construct(FileUrlGeneratorInterface $url_generator) {
    $this->urlGenerator = $url_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('file_url_generator')
    );
  }

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
  public function editFormTitle(OgMenuInstanceInterface $ogmenu_instance): array {
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
    // Use a human version of the delta, i.e. 1-based instead of 0-based.
    $delta--;
    if ($node->get('field_attachment')->get($delta) && $file = $node->get('field_attachment')->get($delta)->entity) {
      $file_url = $this->urlGenerator->generateAbsoluteString($file->getFileUri());
      $response = new CacheableRedirectResponse($file_url);
      $response->addCacheableDependency($node)->addCacheableDependency($file);
      return $response;
    }
    throw new NotFoundHttpException();
  }

}
