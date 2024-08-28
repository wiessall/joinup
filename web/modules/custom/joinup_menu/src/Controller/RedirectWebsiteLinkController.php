<?php

declare(strict_types=1);

namespace Drupal\joinup_menu\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\joinup_group\Entity\GroupInterface;

/**
 * Controller for the website link redirect route.
 */
final class RedirectWebsiteLinkController extends ControllerBase {

  /**
   * Redirects to the website link.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface $node
   *   The group entity.
   *
   * @return \Drupal\Core\Routing\TrustedRedirectResponse
   *   The redirect response.
   */
  public function websiteLink(GroupInterface $node): TrustedRedirectResponse {
    $url = $node->{$node->getWebsiteLinkFieldName()}->uri;
    $response = new TrustedRedirectResponse($url);
    $response->addCacheableDependency($node);
    return $response;
  }

  /**
   * Checks access to the website link.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface $node
   *   The RDF entity.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public static function websiteLinkAccess(GroupInterface $node): AccessResultInterface {
    return AccessResult::allowedIf(!empty($node->{$node->getWebsiteLinkFieldName()}->uri));
  }

}
