<?php

declare(strict_types=1);

namespace Drupal\Tests\sti_graph_explorer\Unit;

use Drupal\sti_graph_explorer\Sparql\Iri;
use Drupal\sti_graph_explorer\Sparql\QueryFactory;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests that request input cannot change the shape of a query.
 *
 * The explorer builds SPARQL from query-string input. A hole here fails
 * silently: a crafted IRI or search term would still return 200, just from
 * a different query (another graph, a UNION with anything).
 *
 * @group sti_graph_explorer
 */
final class QueryInputTest extends UnitTestCase {

  /**
   * IRIs that must be accepted.
   */
  public static function validIris(): array {
    return [
      ['http://data'],
      ['https://sti-portal.fao.org/ontology#Innovation'],
      ['http://sti-portal.fao.org/solution/8d2f821c-371e-4319-a826-3981d999c0f8'],
      ['urn:sti-graph-explorer:untyped'],
      ['http://data.fao.org/ird/dah#use_case'],
      ['https://example.org/path?query=1&x=%20'],
    ];
  }

  /**
   * Strings that must be rejected as IRIs.
   */
  public static function invalidIris(): array {
    return [
      'empty' => [''],
      'relative' => ['/node/1'],
      'closes the IRIREF' => ['http://data> } UNION { ?s ?p ?o'],
      'space' => ['http://data x'],
      'newline' => ["http://data\n"],
      'quote' => ['http://data"'],
      'brace' => ['http://data{'],
      'backslash' => ['http://data\\u003E'],
      'backtick' => ['http://data`'],
      'caret' => ['http://data^'],
      'pipe' => ['http://data|'],
      'too long' => ['http://example.org/' . str_repeat('a', 2048)],
    ];
  }

  /**
   * @dataProvider validIris
   */
  #[DataProvider('validIris')]
  public function testValidIri(string $iri): void {
    $this->assertSame('<' . $iri . '>', Iri::fromString($iri)->toSparql());
  }

  /**
   * @dataProvider invalidIris
   */
  #[DataProvider('invalidIris')]
  public function testInvalidIri(string $iri): void {
    $this->expectException(\InvalidArgumentException::class);
    Iri::fromString($iri);
  }

  /**
   * A search term can never end the string literal it is placed in.
   */
  public function testSearchTermStaysInsideLiteral(): void {
    $hostile = "x\") } UNION { ?s ?p ?o } #\n\\\"'";
    $literal = QueryFactory::literal($hostile);

    // Strip escape sequences; what remains between the outer quotes must
    // contain no quote, backslash or line break.
    $inner = substr($literal, 1, -1);
    $unescaped = preg_replace('/\\\\[tbnrf"\'\\\\]/', '', $inner);
    $this->assertStringStartsWith('"', $literal);
    $this->assertStringEndsWith('"', $literal);
    $this->assertDoesNotMatchRegularExpression('/["\'\\\\\n\r]/', $unescaped);
  }

  /**
   * The instances query uses the escaped term, and clamps limit and offset.
   */
  public function testInstancesQueryUsesEscapedTermAndClamps(): void {
    $query = (new QueryFactory())->instances(
      Iri::fromString('http://data'),
      Iri::fromString('https://sti-portal.fao.org/ontology#Innovation'),
      'Say "Hi"',
      100000,
      -5,
    );
    $this->assertStringContainsString('"say \\"hi\\""', $query);
    $this->assertStringContainsString('LIMIT 201', $query);
    $this->assertStringContainsString('OFFSET 0', $query);
    $this->assertStringContainsString('GRAPH <http://data>', $query);
  }

}
