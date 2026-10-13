<?php

declare(strict_types=1);

namespace Drupal\sti_graph_explorer\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\sti_graph_explorer\GraphExplorer;
use Drupal\sti_graph_explorer\Prefixes;
use Drupal\sti_graph_explorer\Sparql\Iri;
use Drupal\sti_graph_explorer\Sparql\QueryFactory;
use Drupal\sti_graph_explorer\Sparql\SparqlQueryException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * The explorer page and its three JSON endpoints.
 */
final class ExplorerController extends ControllerBase {

  public function __construct(
    private readonly GraphExplorer $explorer,
    #[Autowire(service: 'logger.channel.sti_graph_explorer')]
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * The page: an empty shell the d3 script fills.
   */
  public function page(): array {
    return [
      '#theme' => 'sti_graph_explorer',
      '#attached' => [
        'library' => ['sti_graph_explorer/explorer'],
        'drupalSettings' => [
          'stiGraphExplorer' => [
            'api' => [
              'schema' => Url::fromRoute('sti_graph_explorer.api.schema')->toString(),
              'instances' => Url::fromRoute('sti_graph_explorer.api.instances')->toString(),
              'resource' => Url::fromRoute('sti_graph_explorer.api.resource')->toString(),
            ],
            'untyped' => QueryFactory::UNTYPED,
          ] + Prefixes::forFrontEnd(),
        ],
      ],
      '#cache' => ['contexts' => ['user.permissions']],
    ];
  }

  /**
   * GET schema[?refresh=1].
   */
  public function schema(Request $request): JsonResponse {
    return $this->respond(fn (): array => $this->explorer->schema($request->query->getBoolean('refresh')));
  }

  /**
   * GET instances?class=IRI[&q=text][&offset=N].
   */
  public function instances(Request $request): JsonResponse {
    return $this->respond(fn (): array => $this->explorer->instances(
      Iri::fromString((string) $request->query->get('class', '')),
      mb_substr((string) $request->query->get('q', ''), 0, 200),
      max(0, $request->query->getInt('offset')),
    ));
  }

  /**
   * GET resource?iri=IRI.
   */
  public function resource(Request $request): JsonResponse {
    return $this->respond(fn (): array => $this->explorer->resource(
      Iri::fromString((string) $request->query->get('iri', '')),
    ));
  }

  /**
   * Runs an endpoint body and maps failures to JSON errors.
   *
   * Bad input is the caller's problem (400, not logged). A triplestore
   * failure is ours (502, logged as an error, since the page is then empty).
   */
  private function respond(callable $build): JsonResponse {
    try {
      return new JsonResponse($build());
    }
    catch (\InvalidArgumentException $e) {
      return new JsonResponse(['error' => $e->getMessage()], 400);
    }
    catch (SparqlQueryException $e) {
      $this->logger->error('Graph explorer query failed: @message', ['@message' => $e->getMessage()]);
      return new JsonResponse(['error' => $this->t('The triplestore query failed. Details are in the site log.')], 502);
    }
  }

}
