<?php

declare(strict_types=1);

namespace Drupal\Tests\sti_graph_explorer\Unit;

use Drupal\sti_graph_explorer\Sparql\ResultParser;
use Drupal\sti_graph_explorer\Sparql\SparqlQueryException;
use Drupal\sti_graph_explorer\Sparql\Term;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Psr7\Response;

/**
 * Tests parsing of SPARQL JSON results, and truncation detection.
 *
 * Truncation is the silent failure: Virtuoso answers 200 with partial rows
 * when a query hits MaxQueryExecutionTime, and flags it only in headers.
 *
 * @group sti_graph_explorer
 */
final class ResultParserTest extends UnitTestCase {

  private const BODY = '{"head":{"vars":["s","l","n"]},"results":{"bindings":[
    {"s":{"type":"uri","value":"http://x/1"},"l":{"type":"literal","value":"Farm","xml:lang":"en"},"n":{"type":"typed-literal","datatype":"http://www.w3.org/2001/XMLSchema#integer","value":"42"}},
    {"s":{"type":"bnode","value":"b0"}}
  ]}}';

  /**
   * Terms are typed correctly, including Virtuoso's "typed-literal".
   */
  public function testParsesTerms(): void {
    $result = (new ResultParser())->parse(new Response(200, [], self::BODY));

    $this->assertFalse($result->partial);
    $this->assertCount(2, $result->rows);
    $this->assertSame(Term::IRI, $result->rows[0]['s']->type);
    $this->assertSame('en', $result->rows[0]['l']->lang);
    $this->assertSame(Term::LITERAL, $result->rows[0]['n']->type);
    $this->assertSame(42, $result->rows[0]['n']->toInt());
    $this->assertSame(Term::BNODE, $result->rows[1]['s']->type);
    $this->assertArrayNotHasKey('l', $result->rows[1]);
  }

  /**
   * A 200 carrying Virtuoso's anytime-query headers is marked partial.
   */
  public function testDetectsTruncatedResult(): void {
    $response = new Response(200, [
      'X-SQL-State' => 'S1TAT',
      'X-SQL-Message' => 'RC...: Returning incomplete results, query interrupted by result timeout.',
    ], self::BODY);

    $result = (new ResultParser())->parse($response);

    $this->assertTrue($result->partial);
    $this->assertStringContainsString('S1TAT', $result->partialReason);
    $this->assertCount(2, $result->rows);
  }

  /**
   * Errors and non-JSON bodies throw instead of yielding zero rows.
   */
  public function testFailuresThrow(): void {
    $parser = new ResultParser();
    foreach ([
      new Response(500, [], 'Virtuoso 37000 Error SP030: SPARQL compiler'),
      new Response(200, [], '<html>proxy error</html>'),
      new Response(200, [], '{"boolean": true}'),
    ] as $response) {
      try {
        $parser->parse($response);
        $this->fail('Expected SparqlQueryException for: ' . $response->getBody());
      }
      catch (SparqlQueryException) {
        $this->addToAssertionCount(1);
      }
    }
  }

}
