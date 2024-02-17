<?php

declare(strict_types=1);

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
final class RedirectDocumentationController extends ControllerBase {

  /**
   * Serves a file download.
   *
   * @param \Drupal\rdf_entity\RdfInterface $rdf_entity
   *   The RDF entity.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The file response if the file URL is a file, or a redirect response to
   *   an external URL if the file URL is a URL.
   */
  public function documentationFile(RdfInterface $rdf_entity): Response {
    $fileId = $rdf_entity->field_is_documentation->target_id;
    $file = FileUrlHandler::urlToFile($fileId);

    if (FileUrlHandler::isRemote($file)) {
      $url = $file->getFileUri();
      return (new TrustedRedirectResponse($url))
        ->addCacheableDependency($rdf_entity);
    }
    elseif (!$file) {
      return new RedirectResponse('/404');
    }

    // Finally, if the file URL is a file, serve the file but as a trusted
    // redirect response.
    $response = new BinaryFileResponse($file->getFileUri());
    $response->setContentDisposition('attachment', $file->getFilename());
    // The binary response is cached on the browser side. This is to avoid
    // re-downloading the file when the user clicks on the back button. In order
    // to avoid the file being cached on the browser side, we set the following
    // cache headers.
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

}
