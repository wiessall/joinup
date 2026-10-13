<?php

declare(strict_types=1);

namespace Drupal\sti_graph_explorer;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\sti_graph_explorer\Sparql\Iri;
use Drupal\sti_graph_explorer\Sparql\QueryFactory;
use Drupal\sti_graph_explorer\Sparql\SelectResult;
use Drupal\sti_graph_explorer\Sparql\SparqlClientInterface;
use Drupal\sti_graph_explorer\Sparql\Term;
use Psr\Log\LoggerInterface;

/**
 * Reads the rdf_sync projection and shapes it for the explorer front end.
 */
final class GraphExplorer {

  /**
   * Cache tag for the derived schema. Invalidate it to force a recompute.
   */
  public const SCHEMA_CACHE_TAG = 'sti_graph_explorer:schema';

  /**
   * How long a complete schema stays cached, in seconds.
   *
   * The schema queries scan the whole graph, so they are not run per page
   * view. rdf_sync writes do not invalidate this; the page offers a refresh.
   */
  private const SCHEMA_TTL = 3600;

  private const OUTGOING_LIMIT = 300;
  private const INCOMING_LIMIT = 200;
  private const RDF_TYPE = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type';

  public function __construct(
    private readonly SparqlClientInterface $sparql,
    private readonly QueryFactory $queries,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly CacheBackendInterface $cache,
    private readonly TimeInterface $time,
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * The named graph rdf_sync writes to.
   */
  public function graph(): Iri {
    return Iri::fromString((string) $this->configFactory->get('rdf_sync.settings')->get('graph_uri'));
  }

  /**
   * The schema derived from the data: classes, class links, literal fields.
   *
   * A partial result (the store hit its time limit) is returned with
   * partial = TRUE so the page can say so, and is deliberately not cached:
   * caching it would present truncated counts as complete for an hour.
   *
   * @return array{graph: string, generated: int, partial: bool, partialReason: string, classes: list<array>, links: list<array>, literals: list<array>}
   *   The schema.
   */
  public function schema(bool $refresh = FALSE): array {
    $graph = $this->graph();
    $cid = 'sti_graph_explorer:schema:' . hash('sha256', $graph->value);
    if (!$refresh && ($cached = $this->cache->get($cid))) {
      return $cached->data;
    }

    $counts = $this->sparql->select($this->queries->classCounts($graph));
    $links = $this->sparql->select($this->queries->classLinks($graph));
    $literals = $this->sparql->select($this->queries->classLiterals($graph));

    $schema = [
      'graph' => $graph->value,
      'generated' => $this->time->getRequestTime(),
      'partial' => FALSE,
      'partialReason' => '',
      'classes' => [],
      'links' => [],
      'literals' => [],
    ];
    foreach ([$counts, $links, $literals] as $result) {
      if ($result->partial) {
        $schema['partial'] = TRUE;
        $schema['partialReason'] = $result->partialReason;
      }
    }

    foreach ($counts->rows as $row) {
      if (isset($row['class']) && $row['class']->isIri()) {
        $schema['classes'][] = ['iri' => $row['class']->value, 'count' => $this->int($row, 'n')];
      }
    }
    foreach ($links->rows as $row) {
      if (isset($row['sc'], $row['p'], $row['oc'])) {
        $schema['links'][] = [
          'source' => $row['sc']->value,
          'predicate' => $row['p']->value,
          'target' => $row['oc']->value,
          'count' => $this->int($row, 'n'),
        ];
      }
    }
    foreach ($literals->rows as $row) {
      if (isset($row['sc'], $row['p'])) {
        $schema['literals'][] = [
          'class' => $row['sc']->value,
          'predicate' => $row['p']->value,
          'count' => $this->int($row, 'n'),
          'tagged' => $this->int($row, 'tagged'),
          'datatype' => isset($row['datatype']) ? $row['datatype']->value : '',
        ];
      }
    }

    if ($schema['partial']) {
      $this->logger->warning('Schema for @graph is incomplete; the triplestore truncated a query: @reason', [
        '@graph' => $graph->value,
        '@reason' => $schema['partialReason'],
      ]);
    }
    else {
      $this->cache->set($cid, $schema, $this->time->getRequestTime() + self::SCHEMA_TTL, [self::SCHEMA_CACHE_TAG]);
    }
    return $schema;
  }

  /**
   * One page of instances of a class.
   *
   * @return array{items: list<array{iri: string, label: string}>, more: bool, partial: bool}
   *   The instances, and whether another page exists.
   */
  public function instances(Iri $class, string $search, int $offset, int $limit = 50): array {
    $result = $this->sparql->select($this->queries->instances($this->graph(), $class, $search, $limit, $offset));
    $items = [];
    foreach ($result->rows as $row) {
      if (isset($row['s']) && $row['s']->isIri()) {
        $items[] = ['iri' => $row['s']->value, 'label' => isset($row['label']) ? $row['label']->value : ''];
      }
    }
    $more = count($items) > $limit;
    return ['items' => array_slice($items, 0, $limit), 'more' => $more, 'partial' => $result->partial];
  }

  /**
   * Everything the explorer shows about one resource.
   *
   * @return array
   *   Keys: iri, label, types, literals, out, in, outTruncated, inTotal,
   *   partial, drupal.
   */
  public function resource(Iri $iri): array {
    $graph = $this->graph();
    $out = $this->sparql->select($this->queries->outgoing($graph, $iri, self::OUTGOING_LIMIT));
    $in = $this->sparql->select($this->queries->incoming($graph, $iri, self::INCOMING_LIMIT));
    $inCount = $this->sparql->select($this->queries->incomingCount($graph, $iri));

    $shaped = $this->shapeResource($iri->value, $out, $in);
    $shaped['inTotal'] = isset($inCount->rows[0]) ? $this->int($inCount->rows[0], 'n') : count($shaped['in']);
    $shaped['partial'] = $out->partial || $in->partial || $inCount->partial;
    $shaped['drupal'] = $this->drupalLink($iri->value);
    return $shaped;
  }

  /**
   * Collapses raw neighbourhood rows into one entry per distinct triple.
   *
   * The queries return one row per (triple x type x label), so a node with
   * two types and three labels appears six times. Collapsing here keeps the
   * queries simple and makes the counts the front end shows equal distinct
   * triples.
   *
   * @return array
   *   Keys: iri, label, types, literals, out, in, outTruncated.
   */
  public function shapeResource(string $iri, SelectResult $out, SelectResult $in): array {
    $types = [];
    $ownLabels = [];
    $literals = [];
    $outLinks = [];
    $distinctOut = [];

    foreach ($out->rows as $row) {
      if (!isset($row['p'], $row['o'])) {
        continue;
      }
      $p = $row['p']->value;
      $o = $row['o'];
      $distinctOut[$p . "\0" . $o->type . "\0" . $o->value . "\0" . $o->lang . "\0" . $o->datatype] = TRUE;

      if ($p === self::RDF_TYPE && $o->isIri()) {
        $types[$o->value] = TRUE;
        continue;
      }
      if ($o->isLiteral()) {
        if (in_array($p, QueryFactory::LABEL_PREDICATES, TRUE)) {
          $ownLabels[] = ['predicate' => $p, 'term' => $o];
        }
        $key = $p . "\0" . $o->value . "\0" . $o->lang . "\0" . $o->datatype;
        $literals[$key] = ['predicate' => $p, 'value' => $o->value, 'lang' => $o->lang, 'datatype' => $o->datatype];
        continue;
      }
      $key = $p . "\0" . $o->value;
      $outLinks[$key] ??= ['predicate' => $p, 'iri' => $o->value, 'bnode' => !$o->isIri(), 'types' => [], 'labels' => []];
      $this->collect($outLinks[$key], $row);
    }

    $inLinks = [];
    foreach ($in->rows as $row) {
      if (!isset($row['s'], $row['p'])) {
        continue;
      }
      $key = $row['p']->value . "\0" . $row['s']->value;
      $inLinks[$key] ??= ['predicate' => $row['p']->value, 'iri' => $row['s']->value, 'bnode' => !$row['s']->isIri(), 'types' => [], 'labels' => []];
      $this->collect($inLinks[$key], $row);
    }

    return [
      'iri' => $iri,
      'label' => $this->bestLabel(array_map(static fn (array $l): Term => $l['term'], $ownLabels)),
      'types' => array_keys($types),
      'literals' => array_values($literals),
      'out' => array_values(array_map($this->finishLink(...), $outLinks)),
      'in' => array_values(array_map($this->finishLink(...), $inLinks)),
      'outTruncated' => count($distinctOut) >= self::OUTGOING_LIMIT,
    ];
  }

  /**
   * Finds the Drupal entity rdf_sync minted this IRI for, if the user may see it.
   *
   * Reads rdf_sync's own rdf_sync_uri table directly: it is rdf_sync's
   * IRI-to-entity index, and the entity API offers no lookup by IRI (the
   * URI field is computed, without a column to query). Same approach as
   * sti_dedupe's Merger.
   *
   * @return array{url: string, label: string, entityType: string}|null
   *   The link, or NULL if the IRI is not a Drupal entity or not viewable.
   */
  public function drupalLink(string $iri): ?array {
    $row = $this->database->select('rdf_sync_uri', 'u')
      ->fields('u', ['entity_type', 'entity_id'])
      ->condition('u.uri', $iri)
      ->range(0, 1)
      ->execute()
      ?->fetchAssoc();
    if (!$row) {
      return NULL;
    }

    try {
      $entity = $this->entityTypeManager->getStorage($row['entity_type'])->load($row['entity_id']);
    }
    catch (PluginNotFoundException) {
      return NULL;
    }
    // Paragraphs (claims) have no page of their own: link to the host.
    while ($entity instanceof EntityInterface && !$entity->hasLinkTemplate('canonical') && method_exists($entity, 'getParentEntity')) {
      $entity = $entity->getParentEntity();
    }
    if (!$entity instanceof EntityInterface || !$entity->hasLinkTemplate('canonical') || !$entity->access('view')) {
      return NULL;
    }
    return [
      'url' => $entity->toUrl()->toString(),
      'label' => (string) $entity->label(),
      'entityType' => $entity->getEntityTypeId(),
    ];
  }

  /**
   * Adds one row's type and label to a link entry.
   */
  private function collect(array &$link, array $row): void {
    if (isset($row['t']) && $row['t']->isIri()) {
      $link['types'][$row['t']->value] = TRUE;
    }
    if (isset($row['l']) && $row['l']->isLiteral()) {
      $link['labels'][$row['l']->lang . "\0" . $row['l']->value] = $row['l'];
    }
  }

  /**
   * Replaces a link entry's type set and label candidates with final values.
   */
  private function finishLink(array $link): array {
    $link['types'] = array_keys($link['types']);
    $link['label'] = $this->bestLabel(array_values($link['labels']));
    unset($link['labels']);
    return $link;
  }

  /**
   * Picks a display label: English first, then untagged, then any.
   *
   * @param list<\Drupal\sti_graph_explorer\Sparql\Term> $labels
   *   Candidate label literals.
   */
  private function bestLabel(array $labels): string {
    foreach (['en', ''] as $lang) {
      foreach ($labels as $label) {
        $candidate = strtolower($label->lang);
        if ($candidate === $lang || ($lang !== '' && str_starts_with($candidate, $lang . '-'))) {
          return $label->value;
        }
      }
    }
    return $labels[0]->value ?? '';
  }

  /**
   * Reads an integer binding, 0 if unbound.
   */
  private function int(array $row, string $variable): int {
    return isset($row[$variable]) ? $row[$variable]->toInt() : 0;
  }

}
