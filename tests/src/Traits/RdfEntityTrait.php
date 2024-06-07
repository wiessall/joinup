<?php

declare(strict_types=1);

namespace Drupal\joinup\Traits;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\rdf_entity\Entity\Rdf;
use Drupal\rdf_entity\RdfInterface;

/**
 * Helper methods for using collections in tests.
 */
trait RdfEntityTrait {

  /**
   * Parses human readable fields for RDF entities.
   *
   * This is a convenient wrapper around parseEntityFields() that handles the
   * type casting.
   *
   * @param array $fields
   *   An array of human readable field values.
   *
   * @return array
   *   An array of field data as expected by the field storage handler.
   *
   * @see \Drupal\DrupalExtension\Context\RawDrupalContext::parseEntityFields()
   */
  public function parseRdfEntityFields(array $fields): array {
    $entity = (object) $fields;
    parent::parseEntityFields('rdf_entity', $entity);
    return (array) $entity;
  }

  /**
   * Creates and saves an rdf entity of a specific bundle.
   *
   * @param string $bundle
   *   The rdf entity bundle.
   * @param array $values
   *   An array of field values.
   *
   * @return \Drupal\rdf_entity\RdfInterface
   *   The newly created entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   Thrown when the entity cannot be saved.
   */
  protected static function createRdfEntity(string $bundle, array $values): RdfInterface {
    // Convert timestamp fields from human-readable to timestamp.
    // @todo Replace this with a Behat hook.
    foreach (['changed', 'created', 'published_at'] as $field) {
      if (isset($values[$field])) {
        $date = new DrupalDateTime($values[$field]);
        $values[$field] = $date->getTimestamp();
      }
    }

    $values['rid'] = $bundle;
    $entity = Rdf::create($values);
    $entity->save();

    return $entity;
  }

}
