<?php

declare(strict_types = 1);

namespace Drupal\joinup_menu\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\rdf_entity\RdfInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;

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
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
   *   The file response.
   */
  public function documentationFile(RdfInterface $rdf_entity): BinaryFileResponse {
    $file = $rdf_entity->field_is_documentation->entity;
    $response = new BinaryFileResponse($file->getFileUri());
    $response->setContentDisposition('attachment', $file->getFilename());
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
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   The redirect response.
   */
  public function websiteLink(RdfInterface $rdf_entity): RedirectResponse {
    $url = $rdf_entity->{$rdf_entity->getWebsiteLinkFieldName()}->uri;
    return new RedirectResponse($url);
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
