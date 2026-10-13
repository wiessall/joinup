<?php

declare(strict_types=1);

namespace Drupal\Tests\sti_graph_explorer\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\sti_graph_explorer\GraphExplorer;
use Drupal\sti_graph_explorer\Sparql\QueryFactory;
use Drupal\sti_graph_explorer\Sparql\SelectResult;
use Drupal\sti_graph_explorer\Sparql\SparqlClientInterface;
use Drupal\sti_graph_explorer\Sparql\Term;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests the invariants of GraphExplorer that fail without an error.
 *
 * - A truncated schema must not be cached (it would pass as complete for an
 *   hour), and a complete one must be.
 * - The type x label cross product must collapse to one entry per triple,
 *   or every count and list on the page is inflated.
 *
 * Uses only core interfaces: no Joinup, rdf_sync or triplestore needed.
 *
 * @group sti_graph_explorer
 */
final class GraphExplorerTest extends UnitTestCase {

  private const TYPE = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type';
  private const TITLE = 'http://purl.org/dc/terms/title';

  /**
   * Builds the service with a scripted SPARQL client and a cache mock.
   */
  private function explorer(array $results, CacheBackendInterface $cache, ?LoggerInterface $logger = NULL): GraphExplorer {
    $sparql = $this->createMock(SparqlClientInterface::class);
    $sparql->method('select')->willReturnOnConsecutiveCalls(...$results);
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1000);

    return new GraphExplorer(
      $sparql,
      new QueryFactory(),
      $this->getConfigFactoryStub(['rdf_sync.settings' => ['graph_uri' => 'http://data']]),
      $cache,
      $time,
      $this->createMock(Connection::class),
      $this->createMock(EntityTypeManagerInterface::class),
      $logger ?? $this->createMock(LoggerInterface::class),
    );
  }

  /**
   * One class-count row.
   */
  private function countRow(string $class, int $n): array {
    return ['class' => new Term(Term::IRI, $class), 'n' => new Term(Term::LITERAL, (string) $n)];
  }

  public function testCompleteSchemaIsCached(): void {
    $cache = $this->createMock(CacheBackendInterface::class);
    $cache->method('get')->willReturn(FALSE);
    $cache->expects($this->once())->method('set')
      ->with($this->anything(), $this->callback(fn (array $s): bool => !$s['partial'] && $s['classes'][0]['count'] === 7), 1000 + 3600, [GraphExplorer::SCHEMA_CACHE_TAG]);

    $schema = $this->explorer([
      new SelectResult([$this->countRow('http://x/C', 7)]),
      new SelectResult([]),
      new SelectResult([]),
    ], $cache)->schema();

    $this->assertFalse($schema['partial']);
  }

  public function testTruncatedSchemaIsFlaggedLoggedAndNotCached(): void {
    $cache = $this->createMock(CacheBackendInterface::class);
    $cache->method('get')->willReturn(FALSE);
    $cache->expects($this->never())->method('set');
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())->method('warning');

    $schema = $this->explorer([
      new SelectResult([$this->countRow('http://x/C', 7)]),
      new SelectResult([], TRUE, 'S1TAT timeout'),
      new SelectResult([]),
    ], $cache, $logger)->schema();

    $this->assertTrue($schema['partial']);
    $this->assertSame('S1TAT timeout', $schema['partialReason']);
  }

  public function testResourceRowsCollapseToDistinctTriples(): void {
    $iri = 'http://x/innovation';
    $claim = 'http://x/claim';
    $iri_ = fn (string $v): Term => new Term(Term::IRI, $v);
    $lit = fn (string $v, string $lang = ''): Term => new Term(Term::LITERAL, $v, $lang);

    // The claim has two types and two labels: four rows for one triple.
    $out = new SelectResult([
      ['p' => $iri_(self::TYPE), 'o' => $iri_('http://x/Innovation')],
      ['p' => $iri_(self::TITLE), 'o' => $lit('Farm app', 'fr')],
      ['p' => $iri_(self::TITLE), 'o' => $lit('Farm app EN', 'en')],
      ['p' => $iri_('http://x/hasClaim'), 'o' => $iri_($claim), 't' => $iri_('http://x/A'), 'l' => $lit('one')],
      ['p' => $iri_('http://x/hasClaim'), 'o' => $iri_($claim), 't' => $iri_('http://x/A'), 'l' => $lit('two')],
      ['p' => $iri_('http://x/hasClaim'), 'o' => $iri_($claim), 't' => $iri_('http://x/B'), 'l' => $lit('one')],
      ['p' => $iri_('http://x/hasClaim'), 'o' => $iri_($claim), 't' => $iri_('http://x/B'), 'l' => $lit('two')],
    ]);
    $in = new SelectResult([
      ['s' => $iri_('http://x/other'), 'p' => $iri_('http://x/rel')],
      ['s' => $iri_('http://x/other'), 'p' => $iri_('http://x/rel'), 't' => $iri_('http://x/A')],
    ]);

    $cache = $this->createMock(CacheBackendInterface::class);
    $shaped = $this->explorer([], $cache)->shapeResource($iri, $out, $in);

    $this->assertSame(['http://x/Innovation'], $shaped['types']);
    $this->assertSame('Farm app EN', $shaped['label'], 'English label preferred.');
    $this->assertCount(2, $shaped['literals'], 'Both title literals kept.');
    $this->assertCount(1, $shaped['out'], 'Four rows, one triple.');
    $this->assertSame(['http://x/A', 'http://x/B'], $shaped['out'][0]['types']);
    $this->assertCount(1, $shaped['in']);
    $this->assertFalse($shaped['outTruncated']);
  }

}
