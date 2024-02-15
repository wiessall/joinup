<?php

declare(strict_types=1);

namespace Drupal\rdf_sync\Encoder;

use Drupal\sparql_entity_storage\Encoder\SparqlEncoder;
use EasyRdf\Format;
use EasyRdf\Graph;
use Symfony\Component\Serializer\Encoder\EncoderInterface;

/**
 * Base encoder.
 */
class RdfSyncEncoder implements EncoderInterface {

  /**
   * Memory cache for supported formats.
   *
   * @var \EasyRdf\Serialiser[]
   */
  protected static array $supportedFormats;

  /**
   * {@inheritdoc}
   */
  public function supportsEncoding($format): bool {
    return !empty(static::getSupportedFormats()[$format]);
  }

  /**
   * {@inheritdoc}
   */
  public function encode($data, $format, array $context = []): string {
    // This encoder acts for the same formats as SparqlEncoder. Workaround by
    // copying the logic from SparqlEncoder::encode().
    // @todo Remove this hack in ISAICP-8376.
    // @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-8376
    if (isset($data['_sparql_entity']) || isset($data['message'])) {
      $sparqlEncoder = new SparqlEncoder();
      return $sparqlEncoder->encode($data, $format, $context);
    }

    $graph = new Graph();
    $graph->parse($data, 'php');
    return $graph->serialise($format);
  }

  /**
   * Builds a list of supported formats.
   *
   * @return \EasyRdf\Serialiser[]
   *   List of supported formats.
   */
  public static function getSupportedFormats(): array {
    if (!isset(static::$supportedFormats)) {
      $registeredFormats = array_flip(\Drupal::getContainer()->getParameter('rdf_sync.format'));
      $rdfSerializers = Format::getFormats();
      static::$supportedFormats = array_intersect_key($rdfSerializers, $registeredFormats);
    }
    return static::$supportedFormats;
  }

}
