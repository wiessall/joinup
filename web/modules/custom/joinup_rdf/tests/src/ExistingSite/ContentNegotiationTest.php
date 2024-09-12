<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_rdf\ExistingSite;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\rdf_sync\Encoder\RdfSyncEncoder;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;
use EasyRdf\Format;

/**
 * Tests content negotiation.
 *
 * @group joinup_rdf
 */
class ContentNegotiationTest extends JoinupExistingSiteTestBase {

  /**
   * Memory cache for expected mime types.
   *
   * @var string[]
   */
  protected $mimeType;

  /**
   * Memory cache for expected response bodies.
   *
   * @var string[][]
   */
  protected $expectedBody;

  /**
   * Tests content negotiation.
   */
  public function testContentNegotiation(): void {
    [$term] = array_values(\Drupal::entityTypeManager()->getStorage('taxonomy_term')
      ->loadByProperties(['name' => 'Public domain']));
    $entity = $this->createNode([
      'type' => 'licence',
      'uri' => 'http://example.com/apple',
      'title' => 'Apple',
    ]);

    foreach (RdfSyncEncoder::getSupportedFormats() as $format_name => $format) {
      // An unambiguous RDF mime type as 'Accept' header.
      // Example: application/rdf+xml.
      $mime_type = $format->getDefaultMimeType();
      $this->assertContent($format_name, $entity, $mime_type);
      $this->assertContent($format_name, $term, $mime_type);

      // Save the entities to test if the cache has been cleared.
      $entity->save();
      $term->save();

      // Ambiguous 'Accept' header where the RDF mime type wins.
      // Example: text/html;q=0.9,application/rdf+xml,*/*;q=0.8.
      $accept = "text/html;q=0.9,application/xml;q=0.9,$mime_type,*/*;q=0.8";
      $this->assertContent($format_name, $entity, $accept);
      $this->assertContent($format_name, $term, $accept);

      // Ambiguous 'Accept' header where the 'text/html' mime type wins.
      // Example: text/html,application/xml;q=0.9,application/rdf+xml,*/*;q=0.8.
      $accept = "text/html,application/xml;q=0.9,$mime_type,*/*;q=0.8";
      $this->assertContent('html', $entity, $accept);
      $this->assertContent('html', $term, $accept);
    }

    // Save the entities to clear the cache.
    $entity->save();
    $term->save();

    // Ambiguous 'Accept' header where 'text/html' wins.
    // Example: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8.
    $accept = 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8';
    $this->assertContent('html', $entity, $accept);
    $this->assertContent('html', $term, $accept);

    // Test a non-entity page.
    $this->drupalGet('<front>', [], ['Accept' => 'application/rdf+xml']);
    $this->assertEquals(406, $this->getSession()->getStatusCode());
    $this->assertEquals('Not acceptable format: rdfxml', $this->getBody());
    // Not cached.
    $this->assertNull($this->getSession()->getResponseHeader('X-Drupal-Cache'));
  }

  /**
   * Asserts that content negotiation succeeded.
   *
   * This is a complex/composite assertion that performs a GET request to the
   * $entity canonical page, by sending $accept_header as 'Accept' header value.
   * Three elements are checked twice, once for the non-cached page and twice
   * for the cached version:
   * - If the page has been retrieved from the backend or from cache.
   * - The response 'Content-Type' header.
   * - The response body.
   *
   * @param string $expected_format
   *   The response expected format.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to be tested.
   * @param string $accept_header
   *   The request 'Accept' header value.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   *   If a malformed entity has been passed.
   */
  protected function assertContent(string $expected_format, ContentEntityInterface $entity, string $accept_header): void {
    $this->drupalGet($entity->toUrl(), [], ['Accept' => $accept_header]);
    $session = $this->getSession();

    // Check that the page wasn't cached.
    $this->assertEquals('MISS', $session->getResponseHeader('X-Drupal-Cache'));
    // Check that the response has the correct content type. Usually, the
    // response 'Content-Type' header value is something like:
    // 'text/html; charset=utf-8', so we're testing only the first part.
    $this->assertStringStartsWith($this->getMimeType($expected_format), $session->getResponseHeader('Content-Type'));
    // Check the response body. On 'text/html', we only check the first part.
    if ($expected_format === 'html') {
      $this->assertStringStartsWith('<!DOCTYPE html>', $this->getBody());
    }
    else {
      $this->assertEquals($this->getExpectedBody($expected_format, $entity), $this->getBody());
    }

    // Reload the page to make sure the page has been cached.
    $session->reload();

    // Check that the page was cached.
    $this->assertEquals('HIT', $session->getResponseHeader('X-Drupal-Cache'));
    // Check that we don't get the cache from other format.
    $this->assertStringStartsWith($this->getMimeType($expected_format), $session->getResponseHeader('Content-Type'));
    // Check the response body. On 'text/html', we only check the first part
    // that always is a standard HTML.
    if ($expected_format === 'html') {
      $this->assertStringStartsWith('<!DOCTYPE html>', $this->getBody());
    }
    else {
      $this->assertEquals($this->getExpectedBody($expected_format, $entity), $this->getBody());
    }
  }

  /**
   * Returns the mime type given a format name.
   *
   * @param string $format_name
   *   The format name (e.g. 'html', 'rdfxml', etc).
   *
   * @return string
   *   The mime type.
   */
  protected function getMimeType(string $format_name): string {
    if (!isset($this->mimeType)) {
      $this->mimeType = ['html' => 'text/html'] + array_map(function (Format $format) {
        return $format->getDefaultMimeType();
      }, RdfSyncEncoder::getSupportedFormats());
    }
    return $this->mimeType[$format_name];
  }

  /**
   * Returns the expected response body given an entity and a format name.
   *
   * @param string $format_name
   *   The format name (e.g. 'rdfxml', 'n3', etc).
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return string
   *   The expected response body.
   */
  protected function getExpectedBody(string $expected_format_name, ContentEntityInterface $entity): string {
    if (!isset($this->expectedBody)) {
      $fixtures_dir = __DIR__ . '/../../fixtures/content-negotiation';
      foreach (RdfSyncEncoder::getSupportedFormats() as $format_name => $format) {
        foreach (['node', 'taxonomy_term'] as $entity_type_id) {
          $path = realpath("$fixtures_dir/$entity_type_id/$format_name");
          $this->expectedBody[$entity_type_id][$format_name] = trim(file_get_contents($path));
        }
      }
    }
    return $this->expectedBody[$entity->getEntityTypeId()][$expected_format_name];
  }

  /**
   * Returns the body of the current response.
   *
   * @return string
   *   The body part of the last HTTP response,
   */
  protected function getBody(): string {
    return trim($this->getSession()->getPage()->getContent());
  }

}
