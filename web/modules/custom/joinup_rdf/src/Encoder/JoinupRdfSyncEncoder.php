<?php

declare(strict_types=1);

namespace Drupal\joinup_rdf\Encoder;

use Drupal\rdf_sync\Encoder\RdfSyncEncoder;
use Drupal\sparql_entity_storage\Encoder\SparqlEncoder;

/**
 * Temporary encoder class.
 *
 * @todo Remove this class in ISAICP-8376.
 * @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-8376
 * @see \Drupal\joinup_rdf\JoinupRdfServiceProvider
 */
class JoinupRdfSyncEncoder extends RdfSyncEncoder {

  /**
   * {@inheritdoc}
   */
  public function encode($data, $format, array $context = []): string {
    // This encoder acts for the same formats as SparqlEncoder. Workaround by
    // copying the logic from SparqlEncoder::encode().
    if (isset($data['_sparql_entity']) || isset($data['message'])) {
      $sparqlEncoder = new SparqlEncoder();
      return $sparqlEncoder->encode($data, $format, $context);
    }
    return parent::encode($data, $format, $context);
  }

}
