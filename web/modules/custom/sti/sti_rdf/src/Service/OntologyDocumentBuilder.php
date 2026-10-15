<?php

declare(strict_types=1);

namespace Drupal\sti_rdf\Service;

use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\sti_rdf\StiUri;
use EasyRdf\Graph;
use EasyRdf\Literal;

/**
 * Builds the namespace document served at an STI namespace IRI.
 *
 * The document is generated from what the site actually emits: every class
 * named as an rdf_sync bundle type, and every predicate named in an rdf_sync
 * field mapping, that falls inside the requested namespace. It can therefore
 * never declare a term the site does not use, nor miss one that it does.
 *
 * Axioms that config cannot express (subPropertyOf links to PROV-O, defined
 * classes, ranges of curated terms) live in a hand-written file per
 * namespace, sti_rdf/ontology/{prefix}.ttl, which is merged in verbatim. //? Those don't exist in the ontology, right? 
 */
final class OntologyDocumentBuilder {

  /**
   * Namespaces this site is authoritative for, keyed by prefix.
   */
  public const NAMESPACES = [ //? those are the inputs, but the module writes to a new graph, correct?
    'sti' => StiUri::STI,
    'atio' => StiUri::ATIO,
  ];

  /**
   * Content entity types whose bundles can carry an rdf_sync mapping. 
   */
  private const ENTITY_TYPES = ['node', 'paragraph', 'taxonomy_term'];

  private const RDF = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';
  private const RDFS = 'http://www.w3.org/2000/01/rdf-schema#';
  private const OWL = 'http://www.w3.org/2002/07/owl#';
  private const SCHEMA = 'https://schema.org/';
  private const XSD = 'http://www.w3.org/2001/XMLSchema#';

  public function __construct(
    private readonly MappingReaderInterface $mappingReader,
    private readonly ModuleExtensionList $moduleList,
  ) {}

  /**
   * Builds the document for one namespace prefix.
   *
   * @throws \InvalidArgumentException
   *   When the prefix is not one of self::NAMESPACES.
   */
  public function build(string $prefix): Graph {
    if (!isset(self::NAMESPACES[$prefix])) {
      throw new \InvalidArgumentException(sprintf('Unknown namespace prefix "%s".', $prefix));
    }
    $namespace = self::NAMESPACES[$prefix];
    $document = rtrim($namespace, '#/');

    $graph = new Graph($document);
    $graph->addResource($document, self::RDF . 'type', self::OWL . 'Ontology');
    $graph->addLiteral($document, self::RDFS . 'comment', new Literal('Generated from the rdf_sync mappings of the STI Portal. Declares every term the portal emits in this namespace.', 'en'));

    foreach ($this->mappings() as $mapping) {
      $this->addClass($graph, $namespace, $document, $mapping);
      foreach ($mapping['fields'] as $field) {
        $this->addProperty($graph, $namespace, $document, $mapping, $field);
      }
    }

    $curated = $this->moduleList->getPath('sti_rdf') . "/ontology/$prefix.ttl";
    if (is_file($curated)) {
      $graph->parseFile($curated, 'turtle', $document);
    }

    return $graph;
  }

  /**
   * Declares the bundle's class when it is in the namespace.
   */
  private function addClass(Graph $graph, string $namespace, string $document, array $mapping): void {
    if (!str_starts_with($mapping['type'], $namespace)) {
      return;
    }
    $graph->addResource($mapping['type'], self::RDF . 'type', self::OWL . 'Class');
    $graph->addResource($mapping['type'], self::RDFS . 'isDefinedBy', $document);
    $graph->addLiteral($mapping['type'], self::RDFS . 'label', new Literal($mapping['label'], 'en'));
  }

  /**
   * Declares one mapped predicate when it is in the namespace.
   *
   * A predicate shared by several fields gets one label per field and one
   * schema:domainIncludes per bundle class: that is the honest description
   * of how the site uses it. rdfs:domain is never asserted, because it is an
   * inference rule, not documentation.
   */
  private function addProperty(Graph $graph, string $namespace, string $document, array $mapping, array $field): void {
    $predicate = $field['predicate'];
    if (!str_starts_with($predicate, $namespace)) {
      return;
    }
    $kind = $field['type'] === 'resource' ? 'ObjectProperty' : 'DatatypeProperty';
    $graph->addResource($predicate, self::RDF . 'type', self::OWL . $kind);
    $graph->addResource($predicate, self::RDFS . 'isDefinedBy', $document);
    $graph->addLiteral($predicate, self::RDFS . 'label', new Literal($field['label'], 'en'));
    if ($field['description'] !== '') {
      $graph->addLiteral($predicate, self::RDFS . 'comment', new Literal($field['description'], 'en'));
    }
    if ($mapping['type'] !== '') {
      $graph->addResource($predicate, self::SCHEMA . 'domainIncludes', $mapping['type']);
    }
    if ($field['type'] !== NULL && $field['type'] !== 'resource' && str_starts_with((string) $field['type'], 'xsd:')) {
      $graph->addResource($predicate, self::SCHEMA . 'rangeIncludes', self::XSD . substr((string) $field['type'], 4));
    }
  }

  /**
   * Collects every rdf_sync bundle mapping on the site.
   *
   * @return list<array{type: string, label: string, fields: list<array>}>
   *   One entry per mapped bundle.
   */
  private function mappings(): array {
    $mappings = [];
    foreach ($this->mappingReader->mappedBundles(self::ENTITY_TYPES) as $bundle) {
      $mappings[] = [
        'type' => $bundle['type'],
        'label' => $bundle['label'],
        'fields' => $this->mappingReader->fieldMappings($bundle['entity_type'], $bundle['bundle']),
      ];
    }
    return $mappings;
  }

}
