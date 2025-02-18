<?php

declare(strict_types=1);

namespace Drupal\eira\Controller;

use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\Core\Controller\ControllerBase;
use EasyRdf\Format;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Provides a controller for 'eira.vocabulary' route.
 *
 * The response is cached permanently as data comes from a static file which
 * is part of the codebase. If the source file is changed, that means a new
 * release will be created. Deploying a new release, *always* triggers an
 * explicit cache clear.
 */
class Eira extends ControllerBase {

  /**
   * Returns the EIRA vocabulary as RDF/XML.
   *
   * @return \Drupal\Core\Cache\CacheableResponseInterface
   *   The response as RDF/XML format.
   */
  public function eiraVocabulary(): CacheableResponseInterface {
    return $this->getFileAsResponse('EIRA v6.1.0_SKOS.rdf');
  }

  /**
   * Returns the e-Government Core Vocabularies as RDF/XML.
   *
   * @return \Drupal\Core\Cache\CacheableResponseInterface
   *   The response as RDF/XML format.
   */
  public function egoveraVocabulary(): CacheableResponseInterface {
    return $this->getFileAsResponse('eGovERA.rdf');
  }

  /**
   * Returns a file as a response.
   *
   * @param string $fileName
   *   The file of the vocabulary from the /resources/vocab directory without
   *   the path.
   *
   * @return \Drupal\Core\Cache\CacheableResponseInterface
   *   The response as RDF/XML format.
   */
  private function getFileAsResponse(string $fileName): CacheableResponseInterface {
    $response = new CacheableResponse();
    $response->setContent(file_get_contents(DRUPAL_ROOT . '/../resources/vocab/' . $fileName));

    /** @var \EasyRdf\Format $format */
    $format = Format::getFormats()['rdfxml'];
    $response->headers->set('Content-Type', $format->getDefaultMimeType());

    // Attempt to render the result in browser, if the browser accepts.
    $disposition = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_INLINE, 'eira.' . $format->getDefaultExtension());
    $response->headers->set('Content-Disposition', $disposition);

    return $response;
  }

}
