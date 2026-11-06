<?php

declare(strict_types=1);

namespace Drupal\sti_rdf\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\node\NodeInterface;
use Drupal\rdf_sync\RdfSyncConnectionInterface;
use Drupal\sti_rdf\StiUri;
use EasyRdf\Graph;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Exports a collection's mappings from the graph, as an SSSOM mapping set.
 *
 * Read from the graph rather than built from Drupal, so the file is exactly
 * what consumers see: accepted and rejected (negated) mappings, never
 * proposals, which rdf_sync_published keeps out.
 */
final class MappingExportController implements ContainerInjectionInterface {

  private const SSSOM = 'https://w3id.org/sssom/';

  public function __construct(
    private readonly RdfSyncConnectionInterface $connection,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('rdf_sync.connection'));
  }

  /**
   * Returns the collection's mapping set as Turtle.
   */
  public function export(NodeInterface $node): Response {
    $uri = $node->hasField('uri') ? (string) $node->get('uri')->value : '';
    // Only an STI instance URI goes into the query: it is known to hold no
    // characters that could break out of the IRI.
    if (StiUri::parse($uri) === NULL) {
      throw new NotFoundHttpException();
    }
    // rdf_sync's connection answers SELECT and ASK only, so the triples come
    // back as rows and the graph is assembled here.
    $rows = $this->connection->query(<<<SPARQL
      PREFIX sssom: <https://w3id.org/sssom/>
      SELECT ?m ?p ?o WHERE { <$uri> sssom:mappings ?m . ?m ?p ?o }
      SPARQL);
    $graph = new Graph();
    $graph->addResource($uri, 'rdf:type', self::SSSOM . 'MappingSet');
    foreach ($rows as $row) {
      $graph->addResource($uri, self::SSSOM . 'mappings', (string) $row->m);
      $graph->add((string) $row->m, (string) $row->p, $row->o);
    }
    return new Response((string) $graph->serialise('turtle'), 200, [
      'Content-Type' => 'text/turtle; charset=utf-8',
      'Content-Disposition' => sprintf('attachment; filename="mappings-%d.ttl"', $node->id()),
    ]);
  }

}
