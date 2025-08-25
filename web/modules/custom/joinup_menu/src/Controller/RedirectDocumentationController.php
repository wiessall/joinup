<?php

declare(strict_types=1);

namespace Drupal\joinup_menu\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableRedirectResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\file\FileInterface;
use Drupal\file_url\FileUrlHandler;
use Drupal\joinup_solution\Entity\SolutionInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Returns responses for the joinup_menu routes.
 */
final class RedirectDocumentationController extends ControllerBase {

  /**
   * Serves a file download.
   *
   * @param \Drupal\joinup_solution\Entity\SolutionInterface $node
   *   The solution entity.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The file response if the file URL is a file, or a redirect response to
   *   an external URL if the file URL is a URL.
   */
  public function documentationFile(SolutionInterface $node): Response {
    $resource = $node->getResources()[0] ?? null;
    if ($resource && !$node->get('documentation')->isEmpty()) {
      // If there are resources, we do not allow downloading the documentation
      // file.
      return new RedirectResponse('/404');
    }



    $fileId = $node->documentation->target_id;
    $file = FileUrlHandler::urlToFile($fileId);
    assert($file instanceof FileInterface);

    if (FileUrlHandler::isRemote($file)) {
      $url = $file->getFileUri();
      return (new TrustedRedirectResponse($url))
        ->addCacheableDependency($node);
    }
    elseif (!$file) {
      return new RedirectResponse('/404');
    }

    return (new CacheableRedirectResponse($file->createFileUrl()))
      ->addCacheableDependency($node)
      ->addCacheableDependency($file)
      ->addCacheableDependency((new CacheableMetadata())
        ->addCacheContexts(['user.permissions', 'og_role'])
      );
  }

  /**
   * Checks access to the file download.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The group entity.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public static function documentationFileAccess(NodeInterface $node): AccessResultInterface {
    return AccessResult::allowedIf(!$node->get('documentation')->isEmpty());
  }

}
