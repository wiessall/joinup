<?php

declare(strict_types=1);

namespace Drupal\sti_rdf\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\sti_rdf\Service\OntologyDocumentBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Serves the namespace documents for sti: and atio:.
 *
 * Turtle by default; RDF/XML or N-Triples when the client asks for them in
 * its Accept header. This is what makes the namespace IRIs dereferenceable.
 */
final class OntologyController implements ContainerInjectionInterface {

  /**
   * Media types offered, in order of preference, mapped to EasyRdf formats.
   */
  private const FORMATS = [
    'text/turtle' => 'turtle',
    'application/rdf+xml' => 'rdfxml',
    'application/n-triples' => 'ntriples',
  ];

  public function __construct(
    private readonly OntologyDocumentBuilder $builder,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('sti_rdf.ontology_document_builder'));
  }

  /**
   * Returns one namespace document.
   */
  public function document(Request $request, string $prefix): CacheableResponse {
    $mediaType = $this->negotiate($request);
    $body = $this->builder->build($prefix)->serialise(self::FORMATS[$mediaType]);

    $response = new CacheableResponse((string) $body, 200, [
      'Content-Type' => $mediaType . '; charset=utf-8',
      'Vary' => 'Accept',
    ]);
    $response->addCacheableDependency((new CacheableMetadata())
      ->setCacheContexts(['headers:Accept'])
      ->setCacheTags([
        'config:node_type_list',
        'config:paragraphs_type_list',
        'config:taxonomy_vocabulary_list',
        'config:field_config_list',
      ]));
    return $response;
  }

  /**
   * Picks the first offered media type the client accepts.
   */
  private function negotiate(Request $request): string {
    foreach ($request->getAcceptableContentTypes() as $accepted) {
      if (isset(self::FORMATS[$accepted])) {
        return $accepted;
      }
    }
    return 'text/turtle';
  }

}
