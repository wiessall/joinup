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
   * @param \Drupal\joinup_group\Entity\GroupInterface $group
   *   The group entity.
   *
   * @return \Drupal\Core\Routing\TrustedRedirectResponse
   *   The redirect response.
   */
  public function websiteLink(GroupInterface $group): TrustedRedirectResponse {
    $url = $group->{$group->getWebsiteLinkFieldName()}->uri;
    $response = new TrustedRedirectResponse($url);
    $response->addCacheableDependency($group);
    return $response;
  }

  /**
   * Checks access to the website link.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface $group
   *   The RDF entity.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public static function websiteLinkAccess(GroupInterface $group): AccessResultInterface {
    return AccessResult::allowedIf(!empty($group->{$group->getWebsiteLinkFieldName()}->uri));
  }

}
