<?php

declare(strict_types=1);

namespace Drupal\joinup_seo;

use Drupal\joinup_rdf\Entity\RdfSyncEntityInterface;
use Drupal\rdf_entity\RdfInterface;

/**
 * Interface for services that export RDF data using JSON.
 */
interface JoinupSeoExportHelperInterface {

  /**
   * Exports the given RDF entity as JSON ready to be encapsulated to <head>.
   *
   * @param \Drupal\rdf_entity\RdfInterface $entity
   *   The RDF entity to export.
   *
   * @return string
   *   The exported string.
   *
   * @throws \ML\JsonLD\Exception\JsonLdException
   *   If the entity cannot be serialized.
   *
   * @todo Remove this BC layer built around RDF entities in ISAICP-8376.
   */
  public function exportRdfEntityMetadata(RdfInterface $entity): string;

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
   *
   * @todo Remove BC layer built around RDF entities in ISAICP-8376.
   */
  public function getEntityJsonLdString(RdfSyncEntityInterface|RdfInterface $entity): string;

}
