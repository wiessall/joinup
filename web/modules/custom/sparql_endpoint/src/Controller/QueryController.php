<?php

declare(strict_types=1);

namespace Drupal\sparql_endpoint\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\sparql_endpoint\CachedSparqlReadOnlyEndpoint;
use Drupal\sparql_endpoint\Form\QueryForm;
use Drupal\sparql_endpoint\Plugin\SparqlFormat\SparqlFormatInterface;
use Drupal\sparql_endpoint\SparqlFormatManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
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
    $requestQuery = $request->query->all();
    $build['form'] = $this->formBuilder()->getForm(QueryForm::class);

    $formatResponse = $this->getFormatResponse($request);
    if ($formatResponse && !empty($requestQuery['query'])) {
      $sparqlResponse = $this->endpoint->query($requestQuery['query'], $formatResponse->getMimeType());

      return $formatResponse->getResponse($sparqlResponse);
    }

    return $build;
  }

  /**
   * Get the SparqlFormatInterface response based on the request format.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Drupal\sparql_endpoint\Plugin\SparqlFormat\SparqlFormatInterface|null
   *   Sparql format plugin.
   */
  protected function getFormatResponse(Request $request): ?SparqlFormatInterface {
    $format = $request->getRequestFormat();
    return $this->sparqlFormatManager->getSparqlFormatPluginById($format);
  }

}
