<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_core\ExistingSite;

use PHPUnit\Framework\Attributes\Group;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;
use Drupal\rdf_sync\Encoder\RdfSyncEncoder;

/**
 * Test description.
 */
#[Group('joinup_core')]
final class ExceptionHandleTest extends JoinupExistingSiteTestBase {

  /**
   * Tests that SPARQL formats are supported.
   *
   * @see \Drupal\joinup_rdf\JoinupAcceptHeaderMiddleware::getFormatFromRequest()
   */
  public function testFormatQueryArgument(): void {
    // Test an mapped rdf_sync entity bundle, assert 200 response.
    $entity = $this->createNode([
      'type' => 'collection',
      'field_state' => 'published',
    ]);
    $this->drupalGet($entity->toUrl());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseHeaderEquals('Content-Type', 'text/html; charset=UTF-8');

    $this->drupalGet($entity->toUrl()->setOption('query', ['_format' => 'rdfxml']));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseHeaderEquals('Content-Type', 'application/rdf+xml');

    // Test an unmapped rdf_sync entity bundle, assert 406 response.
    $entity = $this->createNode([
      'type' => 'custom_page',
      'field_state' => 'published',
    ]);
    $this->drupalGet($entity->toUrl());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseHeaderEquals('Content-Type', 'text/html; charset=UTF-8');

    $this->drupalGet($entity->toUrl()->setOption('query', ['_format' => 'rdfxml']));
    $this->assertSession()->statusCodeEquals(406);
    $this->assertSession()->responseHeaderEquals('Content-Type', 'text/plain; charset=UTF-8');
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

    $this->visit('/user/login_status?_format=jsonld');
    $this->assertSession()->statusCodeEquals(404);
    $this->assertSession()->pageTextContains('No route found');
    $this->assertSession()->responseHeaderEquals('Content-Type', 'text/plain; charset=UTF-8');

    foreach (array_keys(RdfSyncEncoder::getSupportedFormats()) as $format) {
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
