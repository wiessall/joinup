<?php

declare(strict_types=1);

namespace Drupal\joinup_seo;

use Drupal\joinup_rdf\Entity\RdfSyncEntityInterface;

/**
 * Interface for services that export RDF data using JSON.
 */
interface JoinupSeoExportHelperInterface {

  /**
   * Exports the given RDF entity as JSON ready to be encapsulated to <head>.
   *
   * @param \Drupal\joinup_rdf\Entity\RdfSyncEntityInterface $entity
   *   The exportable entity.
   *
   * @return string
   *   The exported string.
   *
   * @throws \ML\JsonLD\Exception\JsonLdException
   *   If the entity cannot be serialized.
   */
  public function exportRdfEntityMetadata(RdfSyncEntityInterface $entity): string;

  /**
   * Exports the given RDF (or RDF-synced) entity as JSON.
   *
   * @param \Drupal\joinup_rdf\Entity\RdfSyncEntityInterface $entity
   *   The entity to export.
   *
   * @return string
   *   The exported string.
   *
   * @throws \ML\JsonLD\Exception\JsonLdException
   *    If the entity cannot be serialized.
   */
  public function getEntityJsonLdString(RdfSyncEntityInterface $entity): string;

}
