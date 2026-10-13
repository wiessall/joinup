<?php

declare(strict_types=1);

namespace Drupal\sti_graph_explorer;

/**
 * Namespace prefixes for compact display, and the colour groups they map to.
 *
 * Longest namespace wins, so https://sti-portal.fao.org/ontology/sti-atio#
 * is shown as sti_atio: rather than as sti: with a long local name. Groups
 * are what the legend shows and toggles; order here is colour order.
 */
final class Prefixes {

  public const GROUPS = [
    'sti' => 'STI ontology',
    'sti_data' => 'STI data',
    'ec' => 'EC / Joinup',
    'dcat' => 'DCAT / Dublin Core',
    'skos' => 'SKOS',
    'agents' => 'FOAF / org / schema.org',
    'w3c' => 'RDF / OWL / PROV',
    'other' => 'Other',
  ];

  public const PREFIXES = [
    'sti' => ['https://sti-portal.fao.org/ontology#', 'sti'],
    'sti_atio' => ['https://sti-portal.fao.org/ontology/sti-atio#', 'sti'],
    'core' => ['https://sti-portal.fao.org/collection/core#', 'sti'],
    'stid' => ['http://sti-portal.fao.org/', 'sti_data'],
    'stis' => ['https://sti-portal.fao.org/', 'sti_data'],
    'ird' => ['http://data.fao.org/ird/', 'sti_data'],
    'w21' => ['http://data.europa.eu/w21/', 'ec'],
    'eu' => ['http://data.europa.eu/', 'ec'],
    'euauth' => ['http://publications.europa.eu/resource/authority/', 'ec'],
    'dct' => ['http://purl.org/dc/terms/', 'dcat'],
    'dcat' => ['http://www.w3.org/ns/dcat#', 'dcat'],
    'adms' => ['http://www.w3.org/ns/adms#', 'dcat'],
    'vcard' => ['http://www.w3.org/2006/vcard/ns#', 'dcat'],
    'skos' => ['http://www.w3.org/2004/02/skos/core#', 'skos'],
    'foaf' => ['http://xmlns.com/foaf/0.1/', 'agents'],
    'org' => ['http://www.w3.org/ns/org#', 'agents'],
    'schema' => ['https://schema.org/', 'agents'],
    'rdf' => ['http://www.w3.org/1999/02/22-rdf-syntax-ns#', 'w3c'],
    'rdfs' => ['http://www.w3.org/2000/01/rdf-schema#', 'w3c'],
    'owl' => ['http://www.w3.org/2002/07/owl#', 'w3c'],
    'xsd' => ['http://www.w3.org/2001/XMLSchema#', 'w3c'],
    'prov' => ['http://www.w3.org/ns/prov#', 'w3c'],
  ];

  /**
   * The table in the shape the front end reads.
   *
   * @return array{groups: list<array{id: string, label: string}>, prefixes: list<array{prefix: string, ns: string, group: string}>}
   *   Groups and prefixes, prefixes sorted longest namespace first.
   */
  public static function forFrontEnd(): array {
    $prefixes = [];
    foreach (self::PREFIXES as $prefix => [$ns, $group]) {
      $prefixes[] = ['prefix' => $prefix, 'ns' => $ns, 'group' => $group];
    }
    usort($prefixes, static fn (array $a, array $b): int => strlen($b['ns']) <=> strlen($a['ns']));
    $groups = [];
    foreach (self::GROUPS as $id => $label) {
      $groups[] = ['id' => $id, 'label' => $label];
    }
    return ['groups' => $groups, 'prefixes' => $prefixes];
  }

}
