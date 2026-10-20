<?php

declare(strict_types=1);

namespace Drupal\sti_rdf;

use Drupal\Component\Uuid\Uuid;

/**
 * The single source of truth for STI Portal namespaces and instance URIs.
 *
 * Instance URIs are identity: once a partner, crawler or SPARQL client has
 * seen one, it cannot change. They are therefore built from constants, not
 * configuration, so that edge, review and production mint identical URIs for
 * the same entity, and from a fixed type slug rather than the bundle machine
 * name, so that renaming a bundle never changes what it publishes.
 *
 * Pattern: https://sti-portal.fao.org/id/{type}/{uuid}
 *
 * @see \Drupal\sti_rdf\Plugin\rdf_sync\RdfUriGenerator\StiRdfUriGenerator
 * @see \Drupal\sti_rdf\Controller\IdResolverController
 */
final class StiUri {

  /**
   * Platform vocabulary (tier 1, Unit 5): source records, claims, collections.
   */
  public const STI = 'https://sti-portal.fao.org/sti#';

  /**
   * ATIO vocabulary (tier 2): the curated innovation and its properties.
   */
  public const ATIO = 'https://sti-portal.fao.org/atio#';

  /**
   * Base of every instance URI.
   */
  public const ID_BASE = 'https://sti-portal.fao.org/id/';

  /**
   * Instance types, keyed by the slug that appears in the URI.
   *
   * Adding a type is safe. Renaming or removing one changes the URIs of every
   * entity already minted with it, which is exactly what this class exists to
   * prevent, so treat the keys as frozen once anything is published.
   */
  public const TYPES = [
    'source-record' => 'Source record (solution)',
    'innovation' => 'ATIO innovation',
    'annotation' => 'Annotation (legacy bundles)',
    'collection' => 'Collection / data source',
    'agent' => 'Agent (owner organisation)',
    'claim' => 'Field claim',
    'property' => 'Collection-scoped claim property',
    'concept' => 'Vocabulary concept',
    'mapping' => 'SSSOM mapping',
  ];

  /**
   * URI prefixes minted before P0, rewritten by the URI reminter.
   *
   * - http://sti-portal.fao.org/{bundle}/{uuid}: the first sti_namespace.
   * - http://data.europa.eu/w21/{uuid}: Joinup's Publications Office
   *   namespace, inherited by collection and owner. Not ours to mint in.
   * - http://data.fao.org/ird/: the namespace ird_claims tried to write.
   */
  public const LEGACY_PREFIXES = [
    'http://sti-portal.fao.org/',
    'http://data.europa.eu/w21/',
    'http://data.fao.org/ird/',
  ];

  /**
   * Builds the instance URI of an entity.
   *
   * @param string $type
   *   One of the keys of self::TYPES.
   * @param string $uuid
   *   The entity UUID.
   *
   * @return string
   *   The absolute instance URI.
   *
   * @throws \InvalidArgumentException
   *   When the type is unknown or the UUID is malformed. Both are loud on
   *   purpose: a malformed identity must never be written to rdf_sync_uri.
   */
  public static function forEntity(string $type, string $uuid): string {
    if (!isset(self::TYPES[$type])) {
      throw new \InvalidArgumentException(sprintf('Unknown STI URI type "%s". Known types: %s.', $type, implode(', ', array_keys(self::TYPES))));
    }
    // Normalise first: Uuid::isValid() accepts lower case only, and one
    // entity must have exactly one URI.
    $uuid = strtolower($uuid);
    if (!Uuid::isValid($uuid)) {
      throw new \InvalidArgumentException(sprintf('"%s" is not a valid UUID.', $uuid));
    }
    return self::ID_BASE . $type . '/' . $uuid;
  }

  /**
   * Splits an instance URI into its type and UUID.
   *
   * @return array{type: string, uuid: string}|null
   *   The parts, or NULL when the URI is not an STI instance URI.
   */
  public static function parse(string $uri): ?array {
    if (!str_starts_with($uri, self::ID_BASE)) {
      return NULL;
    }
    $parts = explode('/', substr($uri, strlen(self::ID_BASE)));
    if (count($parts) !== 2 || !isset(self::TYPES[$parts[0]]) || !Uuid::isValid($parts[1])) {
      return NULL;
    }
    return ['type' => $parts[0], 'uuid' => $parts[1]];
  }

  /**
   * Whether a URI was minted under one of the pre-P0 prefixes.
   *
   * @param string $uri
   *   The URI.
   * @param string[] $extraPrefixes
   *   Site-specific prefixes to count as pre-P0 as well.
   */
  public static function isLegacy(string $uri, array $extraPrefixes = []): bool {
    foreach ([...self::LEGACY_PREFIXES, ...$extraPrefixes] as $prefix) {
      if (str_starts_with($uri, $prefix)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Returns the regular expression matching every type slug, for routing.
   */
  public static function typeRequirement(): string {
    // Type slugs are lower-case letters and hyphens only (asserted in
    // StiUriTest), so they are used in the pattern as they are.
    return implode('|', array_keys(self::TYPES));
  }

}
