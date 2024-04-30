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
   * Checks the number of available RDF entities filtered by bundle.
   *
   * @param int $number
   *   The expected number of RDF entities.
   * @param string $type
   *   The RDF type.
   *
   * @throws \Exception
   *   Thrown when the number of RDF entities does not
   *   match the expectation.
   */
  protected function assertRdfEntityCount(int $number, string $type): void {
    $actual = \Drupal::entityQuery('rdf_entity')
      ->accessCheck(FALSE)
      ->condition('rid', $type)
      ->count()
      ->execute();
    if ($actual != $number) {
      throw new \Exception("Wrong number of $type entities. Expected number: $number, actual number: $actual.");
    }
  }

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
    foreach (['changed', 'created'] as $field) {
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
