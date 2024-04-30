<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_federation\Kernel;

use EasyRdf\Graph;

/**
 * Tests the 'remove_unsupported_data' process step plugin.
 *
 * @group joinup_federation
 *
 * @todo The whole federation functionality will be removed. For now just
 *   disable testing. A final decision will be made in ISAICP-8376.
 * @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-8376
 * @group wip
 */
class RemoveUnsupportedDataStepTest extends StepTestBase {

  /**
   * {@inheritdoc}
   */
  protected function getUsedStepPlugins(): array {
    return ['remove_unsupported_data' => []];
  }

  /**
   * Test ADMSv2 changes.
   */
  public function test() {
    $graph = new Graph(static::getTestingGraphs()['sink']);
    $graph->parseFile(__DIR__ . '/../../fixtures/valid_adms.rdf');
    $this->createGraphStore()->replace($graph);

    $this->runPipelineStep('remove_unsupported_data');

    $query = <<<Query
SELECT ?graph ?subject ?predicate ?object
FROM NAMED <{$this->getTestingGraphs()['sink']}>
WHERE {
  GRAPH ?graph { ?subject ?predicate ?object }
}
Query;

    $unsupported_triples_count = 0;
    foreach ($this->sparql->query($query) as $triple) {
      // Only http://vocabulary/term triples are unsupported in the test file.
      if ($triple->subject->getUri() == 'http://vocabulary/term') {
        $unsupported_triples_count++;
      }
    }

    // Check that all remaining triples are supported.
    $this->assertEquals(0, $unsupported_triples_count);
  }

}
