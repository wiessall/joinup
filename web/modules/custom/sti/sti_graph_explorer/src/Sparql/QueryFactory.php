<?php

declare(strict_types=1);

namespace Drupal\sti_graph_explorer\Sparql;

/**
 * Builds the fixed set of queries the explorer runs.
 *
 * Every query is scoped to one named graph. Inputs enter only as validated
 * Iri objects, a string literal built by literal(), or clamped integers.
 */
final class QueryFactory {

  /**
   * Stands in for "an IRI object that has no rdf:type in the graph".
   */
  public const UNTYPED = 'urn:sti-graph-explorer:untyped';

  /**
   * Predicates read as a human label, in order of preference.
   */
  public const LABEL_PREDICATES = [
    'http://purl.org/dc/terms/title',
    'http://www.w3.org/2004/02/skos/core#prefLabel',
    'http://www.w3.org/2000/01/rdf-schema#label',
    'http://xmlns.com/foaf/0.1/name',
    'https://schema.org/name',
  ];

  private const RDF_TYPE = '<http://www.w3.org/1999/02/22-rdf-syntax-ns#type>';

  /**
   * Instances per class: one row per typed subject.
   */
  public function classCounts(Iri $graph): string {
    $g = $graph->toSparql();
    $type = self::RDF_TYPE;
    return <<<SPARQL
      SELECT ?class (COUNT(DISTINCT ?s) AS ?n)
      WHERE { GRAPH $g { ?s $type ?class } }
      GROUP BY ?class
      SPARQL;
  }

  /**
   * Links between classes: subject class, predicate, object class, triples.
   *
   * An object without a type is reported under UNTYPED, so links that end
   * outside the graph (or at something rdf_sync does not project) stay
   * visible instead of disappearing. A subject with two types is counted
   * under each.
   */
  public function classLinks(Iri $graph): string {
    $g = $graph->toSparql();
    $type = self::RDF_TYPE;
    $untyped = '<' . self::UNTYPED . '>';
    return <<<SPARQL
      SELECT ?sc ?p ?oc (COUNT(*) AS ?n)
      WHERE {
        GRAPH $g {
          ?s ?p ?o .
          FILTER (isIRI(?o) && ?p != $type)
          ?s $type ?sc .
          OPTIONAL { ?o $type ?t }
        }
        BIND (COALESCE(?t, $untyped) AS ?oc)
      }
      GROUP BY ?sc ?p ?oc
      SPARQL;
  }

  /**
   * Literal-valued predicates per class, with language-tag and datatype use.
   */
  public function classLiterals(Iri $graph): string {
    $g = $graph->toSparql();
    $type = self::RDF_TYPE;
    return <<<SPARQL
      SELECT ?sc ?p (COUNT(*) AS ?n)
             (SUM(IF(LANG(?o) != "", 1, 0)) AS ?tagged)
             (SAMPLE(IF(LANG(?o) != "", "", STR(DATATYPE(?o)))) AS ?datatype)
      WHERE {
        GRAPH $g {
          ?s ?p ?o .
          FILTER isLiteral(?o)
          ?s $type ?sc .
        }
      }
      GROUP BY ?sc ?p
      SPARQL;
  }

  /**
   * One page of instances of a class, optionally filtered by label or IRI.
   *
   * Asks for one row more than $limit so the caller can tell whether another
   * page exists without a separate COUNT.
   */
  public function instances(Iri $graph, Iri $class, string $search, int $limit, int $offset): string {
    $g = $graph->toSparql();
    $c = $class->toSparql();
    $type = self::RDF_TYPE;
    $labels = $this->labelPredicateList();
    $limit = max(1, min($limit, 200)) + 1;
    $offset = max(0, $offset);

    $filter = '';
    $search = mb_strtolower(trim($search));
    if ($search !== '') {
      $needle = self::literal($search);
      $filter = "FILTER (CONTAINS(LCASE(STR(?l)), $needle) || CONTAINS(LCASE(STR(?s)), $needle))";
    }

    // Grouping happens in a subquery and ordering outside it: Virtuoso
    // rejects ORDER BY on an expression over an aggregate alias of the same
    // query level.
    return <<<SPARQL
      SELECT ?s ?label
      WHERE {
        {
          SELECT ?s (SAMPLE(?l) AS ?label)
          WHERE {
            GRAPH $g {
              ?s $type $c .
              OPTIONAL { ?s ?lp ?l . FILTER (?lp IN ($labels)) }
            }
            $filter
          }
          GROUP BY ?s
        }
      }
      ORDER BY LCASE(COALESCE(STR(?label), "")) ?s
      LIMIT $limit
      OFFSET $offset
      SPARQL;
  }

  /**
   * Outgoing triples of a resource, with each IRI object's types and labels.
   *
   * The LIMIT sits in a subquery on the triples themselves; applied to the
   * outer query it would cut the type/label cross product arbitrarily.
   */
  public function outgoing(Iri $graph, Iri $resource, int $limit): string {
    $g = $graph->toSparql();
    $r = $resource->toSparql();
    $type = self::RDF_TYPE;
    $labels = $this->labelPredicateList();
    $limit = max(1, min($limit, 1000));
    return <<<SPARQL
      SELECT ?p ?o ?t ?l
      WHERE {
        { SELECT ?p ?o WHERE { GRAPH $g { $r ?p ?o } } LIMIT $limit }
        OPTIONAL { GRAPH $g { ?o $type ?t } }
        OPTIONAL { GRAPH $g { ?o ?lp ?l . FILTER (?lp IN ($labels)) } }
      }
      SPARQL;
  }

  /**
   * Incoming triples of a resource, with each subject's types and labels.
   */
  public function incoming(Iri $graph, Iri $resource, int $limit): string {
    $g = $graph->toSparql();
    $r = $resource->toSparql();
    $type = self::RDF_TYPE;
    $labels = $this->labelPredicateList();
    $limit = max(1, min($limit, 1000));
    return <<<SPARQL
      SELECT ?s ?p ?t ?l
      WHERE {
        { SELECT ?s ?p WHERE { GRAPH $g { ?s ?p $r } } LIMIT $limit }
        OPTIONAL { GRAPH $g { ?s $type ?t } }
        OPTIONAL { GRAPH $g { ?s ?lp ?l . FILTER (?lp IN ($labels)) } }
      }
      SPARQL;
  }

  /**
   * Total number of incoming triples (a vocabulary term can have thousands).
   */
  public function incomingCount(Iri $graph, Iri $resource): string {
    $g = $graph->toSparql();
    $r = $resource->toSparql();
    return <<<SPARQL
      SELECT (COUNT(*) AS ?n) WHERE { GRAPH $g { ?s ?p $r } }
      SPARQL;
  }

  /**
   * Quotes a string as a SPARQL string literal.
   *
   * Escapes every character that could end the literal or the line
   * (SPARQL 1.1 ECHAR), so the value cannot break out of the quotes.
   */
  public static function literal(string $value): string {
    $escaped = strtr($value, [
      '\\' => '\\\\',
      '"' => '\\"',
      "'" => "\\'",
      "\n" => '\\n',
      "\r" => '\\r',
      "\t" => '\\t',
      "\x08" => '\\b',
      "\x0C" => '\\f',
    ]);
    return '"' . $escaped . '"';
  }

  /**
   * The label predicates as a comma-separated list of IRIREFs.
   */
  private function labelPredicateList(): string {
    return implode(', ', array_map(static fn (string $p): string => "<$p>", self::LABEL_PREDICATES));
  }

}
