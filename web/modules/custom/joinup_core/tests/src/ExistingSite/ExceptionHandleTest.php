<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_core\ExistingSite;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;
use Drupal\sparql_entity_storage\Encoder\SparqlEncoder;

/**
 * Test description.
 *
 * @group joinup_core
 */
final class ExceptionHandleTest extends JoinupExistingSiteTestBase {

  /**
   * Tests that SPARQL formats are supported.
   *
   * @see \Drupal\joinup_sparql\JoinupAcceptHeaderMiddleware::getFormatFromRequest()
   */
  public function testFormatQueryArgument(): void {
    // Create an RDF entity.
    $entity = $this->createRdfEntity([
      'rid' => 'collection',
      'field_ar_state' => 'published',
    ]);
    $this->assertNotEmpty($entity->id());
    $this->assertNotEmpty(\Drupal::entityTypeManager()->getStorage('rdf_entity')->load($entity->id()));

    $this->visit($entity->toUrl('canonical')->setAbsolute()->toString());
    $this->assertSession()->statusCodeEquals(200);
    // Assert that the page is in HTML format.
    $this->assertSession()->responseHeaderEquals('Content-Type', 'text/html; charset=UTF-8');

    $query = ['query' => ['_format' => 'rdfxml']];
    $this->visit($entity->toUrl('canonical', $query)->setAbsolute()->toString());
    $this->assertSession()->statusCodeEquals(200);
    // Assert that the page is in RDFXML format.
    $this->assertSession()->responseHeaderEquals('Content-Type', 'application/rdf+xml');
  }

  /**
   * Test callback.
   */
  public function testInvalidFormat(): void {
    $this->visit('/index.php?_format=abc');
    $this->assertSession()->statusCodeEquals(406);
    $this->assertSession()->pageTextContains('Not acceptable format: abc');
    $this->assertSession()->responseHeaderEquals('Content-Type', 'text/plain; charset=UTF-8');
    $this->assertNoExceptionsLogged('abc');

    $this->visit('/index.php?_format=html');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextNotContains('Not acceptable format: html');
    $this->assertSession()->responseHeaderEquals('Content-Type', 'text/html; charset=UTF-8');
    $this->assertNoExceptionsLogged('html');

    $this->visit('/index.php?_format=turtle');
    $this->assertSession()->statusCodeEquals(406);
    $this->assertSession()->pageTextContains('Not acceptable format: turtle');
    $this->assertSession()->responseHeaderEquals('Content-Type', 'text/turtle; charset=UTF-8');
    $this->assertNoExceptionsLogged('turtle');

    foreach (array_keys(SparqlEncoder::getSupportedFormats()) as $format) {
      // Regression test that passing rdfxml does not cause an empty format type
      // case.
      $this->visit('/non-existing-page?_format=' . $format);
      $this->assertSession()->statusCodeEquals(404);
      $this->assertSession()->pageTextContains('No route found for');
      $this->assertSession()->responseHeaderEquals('Content-Type', 'text/plain; charset=UTF-8');
    }
  }

  /**
   * Asserts that no exceptions were logged.
   *
   * @param string $format
   *   The format.
   */
  protected function assertNoExceptionsLogged(string $format): void {
    // Load messages from watchdog that are at least errors.
    $messages = \Drupal::database()
      ->select('watchdog', 'w')
      ->fields('w', ['message'])
      ->condition('w.severity', RfcLogLevel::ERROR, '<=')
      ->condition('w.message', '%Not acceptable format: ' . $format . '%', 'LIKE')
      ->execute()
      ->fetchCol();

    $this->assertEmpty($messages, 'No exceptions were logged.');
  }

  /**
   * {@inheritdoc}
   */
  public function tearDown(): void {
    parent::tearDown();

    // Remove all not acceptable format messages from watchdog.
    \Drupal::database()
      ->delete('watchdog')
      ->condition('message', '%Not acceptable format:%', 'LIKE')
      ->execute();
  }

}
