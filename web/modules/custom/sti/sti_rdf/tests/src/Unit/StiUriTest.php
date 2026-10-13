<?php

declare(strict_types=1);

namespace Drupal\Tests\sti_rdf\Unit;

use Drupal\sti_rdf\StiUri;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests the instance URI rules.
 *
 * Identity bugs are silent: a wrong URI is minted, synced and cited before
 * anyone notices. These assertions pin the pattern down.
 */
#[CoversClass(StiUri::class)]
#[Group('sti_rdf')]
final class StiUriTest extends UnitTestCase {

  private const UUID = '0b8e3c1e-6f0a-4c39-9a3b-6f5d2a7c9e10';

  /**
   * The pattern is https, /id/, a fixed type slug, then the UUID.
   */
  public function testForEntityBuildsThePublishedPattern(): void {
    $this->assertSame(
      'https://sti-portal.fao.org/id/source-record/' . self::UUID,
      StiUri::forEntity('source-record', self::UUID),
    );
    // UUIDs are normalised to lower case, so one entity has one URI.
    $this->assertSame(
      StiUri::forEntity('innovation', self::UUID),
      StiUri::forEntity('innovation', strtoupper(self::UUID)),
    );
  }

  /**
   * Unknown types and malformed UUIDs are refused, never written.
   */
  public function testForEntityRefusesMalformedInput(): void {
    foreach ([['solution', self::UUID], ['innovation', 'not-a-uuid']] as [$type, $uuid]) {
      try {
        StiUri::forEntity($type, $uuid);
        $this->fail("forEntity('$type', '$uuid') should have thrown.");
      }
      catch (\InvalidArgumentException) {
        $this->addToAssertionCount(1);
      }
    }
  }

  /**
   * Parse is the exact inverse of forEntity, and rejects everything else.
   */
  public function testParseRoundTrips(): void {
    foreach (array_keys(StiUri::TYPES) as $type) {
      $this->assertSame(['type' => $type, 'uuid' => self::UUID], StiUri::parse(StiUri::forEntity($type, self::UUID)));
    }
    $this->assertNull(StiUri::parse('http://sti-portal.fao.org/id/innovation/' . self::UUID));
    $this->assertNull(StiUri::parse('https://sti-portal.fao.org/id/unknown/' . self::UUID));
    $this->assertNull(StiUri::parse('https://sti-portal.fao.org/id/innovation/' . self::UUID . '/extra'));
  }

  /**
   * Every pre-P0 prefix is recognised; new and external URIs are not.
   */
  public function testIsLegacy(): void {
    $this->assertTrue(StiUri::isLegacy('http://sti-portal.fao.org/solution/' . self::UUID));
    $this->assertTrue(StiUri::isLegacy('http://data.europa.eu/w21/' . self::UUID));
    $this->assertTrue(StiUri::isLegacy('http://data.fao.org/ird/wocat#readiness'));
    $this->assertFalse(StiUri::isLegacy(StiUri::forEntity('claim', self::UUID)));
    $this->assertFalse(StiUri::isLegacy('http://aims.fao.org/aos/agrovoc/c_8113'));
    // Joinup's other PO namespace is not ours to touch.
    $this->assertFalse(StiUri::isLegacy('http://data.europa.eu/s2k/' . self::UUID));
  }

  /**
   * The route requirement lists exactly the known types.
   *
   * The route YAML cannot reference the class constant, so the two can
   * drift apart: a type added to StiUri::TYPES but not to the route would
   * mint URIs that 404.
   */
  public function testRouteRequirementMatchesTypes(): void {
    $routes = Yaml::parseFile(dirname(__DIR__, 3) . '/sti_rdf.routing.yml');
    $requirement = $routes['sti_rdf.id']['requirements']['type'];
    $this->assertEqualsCanonicalizing(array_keys(StiUri::TYPES), explode('|', $requirement));
    $this->assertSame(StiUri::typeRequirement(), $requirement);
    foreach (array_keys(StiUri::TYPES) as $type) {
      $this->assertMatchesRegularExpression('/^[a-z][a-z-]*$/', $type, 'Type slugs must be safe in a URI path and a route pattern without escaping.');
    }
  }

}
