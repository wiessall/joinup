<?php

declare(strict_types=1);

namespace Drupal\sparql_endpoint\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\sparql_endpoint\CachedSparqlReadOnlyEndpoint;
use Drupal\sparql_endpoint\Form\QueryForm;
use Drupal\sparql_endpoint\SparqlFormatManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\AcceptHeader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provides a Sparql query controller.
 */
class QueryController extends ControllerBase {

  public function __construct(
    protected CachedSparqlReadOnlyEndpoint $endpoint,
    protected SparqlFormatManager $sparqlFormatManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('sparql_endpoint.cached_sparql_endpoint'),
      $container->get('plugin.manager.sparql_endpoint.sparql_format')
    );
  }

  /**
   * Handles the content request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return array|\Symfony\Component\HttpFoundation\Response
   *   The rendered content or an HTTP response.
   */
  public function content(Request $request): array|Response {
    $query = $request->query->get('query');
    $format = $this->getFormatFromQuery($request) ?? $this->getFormatFromRequest($request);

    $sparqlFormat = $this->sparqlFormatManager->getSparqlFormatPluginById($format);
    if (!$sparqlFormat) {
      return new Response('Bad Request. Unknown format', Response::HTTP_BAD_REQUEST);
    }
    if (empty($query)) {
      $build['form'] = $this->formBuilder()->getForm(QueryForm::class);
      return $build;
    }

    $sparqlResponse = $this->endpoint->query($query, $sparqlFormat->getPluginId());
    if (!$sparqlResponse->isSuccess()) {
      return new Response($sparqlResponse->results, $sparqlResponse->code, [
        'Content-Type' => 'text/plain',
      ]);
    }

    return $sparqlFormat->getResponse($sparqlResponse);
  }

  /**
   * Gets the format from the query.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return string|null
   *   The format value retrieved from the Accept header.
   */
  protected function getFormatFromQuery(Request $request): ?string {
    return $request->query->get('format');
  }

  /**
   * Gets the format from the request's Accept header.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return string
   *   The format value retrieved from the Accept header.
   */
  protected function getFormatFromRequest(Request $request): string {
    $acceptHeaderString = $request->headers->get('Accept') ?: 'text/html';
    $acceptHeader = AcceptHeader::fromString($acceptHeaderString);

    return $acceptHeader->first()->getValue();
  }

}
