<?php

declare(strict_types=1);

namespace Drupal\custom_page\Controller;

use Drupal\Core\Cache\CacheableRedirectResponse;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for custom pages.
 */
class CustomPageController implements ContainerInjectionInterface {

  use StringTranslationTrait;
  use AutowireTrait;

  public function __construct(
    protected readonly FileUrlGeneratorInterface $urlGenerator,
  ) {}

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
