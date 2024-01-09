<?php

declare(strict_types = 1);

namespace Drupal\joinup_menu\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\file_url\FileUrlHandler;
use Drupal\rdf_entity\RdfInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Returns responses for the joinup_menu routes.
 */
final class JoinupMenuController extends ControllerBase {

  /**
   * Serves a file download.
   *
   * @param \Drupal\rdf_entity\RdfInterface $rdf_entity
   *   The RDF entity.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The file response or a page not found response.
   */
  public function documentationFile(RdfInterface $rdf_entity): Response {
    $fileId = $rdf_entity->field_is_documentation->target_id;
    $file = FileUrlHandler::urlToFile($fileId);
    if (!$file) {
      return new RedirectResponse('/404');
    }

    $response = new BinaryFileResponse($file->getFileUri());
    $response->setContentDisposition('attachment', $file->getFilename());
    $response->setCache([
      'no_cache' => TRUE,
      'max_age' => 0,
      'no_store' => TRUE,
    ]);
    return $response->send();
  }

  /**
   * Checks access to the file download.
   *
   * @param \Drupal\rdf_entity\RdfInterface $rdf_entity
   *   The RDF entity.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public static function documentationFileAccess(RdfInterface $rdf_entity): AccessResultInterface {
    return AccessResult::allowedIf(!$rdf_entity->get('field_is_documentation')->isEmpty());
  }

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
    return new TrustedRedirectResponse($url);
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
