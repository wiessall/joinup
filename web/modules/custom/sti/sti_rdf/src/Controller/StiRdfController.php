<?php

declare(strict_types=1);

namespace Drupal\sti_rdf\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Returns responses for STI RDF routes.
 */
final class StiRdfController extends ControllerBase {

  /**
   * Builds the response.
   */
  // public function __invoke(): array {

  //   $build['content'] = [
  //     '#type' => 'item',
  //     '#markup' => $this->t('It works!'),
  //   ];

  //   return $build;
  // }
  public function curate(): Response {
    $html = file_get_contents(__DIR__ . '/../../assets/skos_curation.html');
    return new Response ($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
  }

}
