<?php

declare(strict_types=1);

namespace Drupal\joinup_menu\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\rdf_entity\RdfInterface;

/**
 * Controller for the website link redirect route.
 */
final class RedirectWebsiteLinkController extends ControllerBase {

  /**
   * Redirects to the website link.
   *
   * @param \Drupal\rdf_entity\RdfInterface $rdf_entity
   *   The RDF entity.
   *
   * @return \Drupal\Core\Routing\TrustedRedirectResponse
   *   The redirect response.
   */
  public function websiteLink(RdfInterface $rdf_entity): TrustedRedirectResponse {
    $url = $rdf_entity->{$rdf_entity->getWebsiteLinkFieldName()}->uri;
    $response = new TrustedRedirectResponse($url);
    $response->addCacheableDependency($rdf_entity);
    return $response;
  }

  /**
   * Checks access to the website link.
   *
   * @param \Drupal\rdf_entity\RdfInterface $rdf_entity
   *   The RDF entity.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public static function websiteLinkAccess(RdfInterface $rdf_entity): AccessResultInterface {
    return AccessResult::allowedIf(!empty($rdf_entity->{$rdf_entity->getWebsiteLinkFieldName()}->uri));
  }

}
