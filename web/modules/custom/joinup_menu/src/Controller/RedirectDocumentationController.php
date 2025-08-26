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
use Drupal\link\LinkItemInterface;
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
   * @param \Drupal\node\NodeInterface $node
   *   The solution entity.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The file response if the file URL is a file, or a redirect response to
   *   an external URL if the file URL is a URL.
   */
  public function documentationFile(NodeInterface $node): Response {
    /** @var \Drupal\joinup_solution\Entity\SolutionInterface $node */
    if ($node->get('resources')->isEmpty()) {
      // If there are resources, we do not allow downloading the documentation
      // file.
      return new RedirectResponse('/404');
    }

    $resources = $node->getResources();
    // Solutions only have one documentation file.
    $resource = reset($resources);
    if ($resource instanceof LinkItemInterface) {
      return (new TrustedRedirectResponse($resource->uri))
        ->addCacheableDependency($node);
    }

    if (!$resource) {
      // Handle the case that the file has been deleted but the paragraph
      // reference still exists.
      return new RedirectResponse('/404');
    }

    assert($resource instanceof FileInterface);
    return (new CacheableRedirectResponse($resource->createFileUrl()))
      ->addCacheableDependency($node)
      ->addCacheableDependency($resource)
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
    return AccessResult::allowedIf(!$node->get('resources')->isEmpty())->addCacheableDependency($node);
  }

}
