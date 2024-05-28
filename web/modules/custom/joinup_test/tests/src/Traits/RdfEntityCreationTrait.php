<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_test\Traits;

use Drupal\Tests\rdf_entity\Traits\DrupalTestTraits\RdfEntityCreationTrait as ContribRdfEntityCreationTrait;
use Drupal\rdf_entity\RdfInterface;

/**
 * Wraps the `rdf_entity` module RDF entity creation trait.
 */
trait RdfEntityCreationTrait {

  use ContribRdfEntityCreationTrait {
    createRdfEntity as contribCreateRdfEntity;
  }

  /**
   * State fields per bundle.
   *
   * @const string[]
   */
  protected const STATE_FIELDS = [
    'collection' => 'field_ar_state',
    'solution' => 'field_is_state',
    'asset_release' => 'field_isr_state',
    'asset_distribution' => 'field_ad_state',
    'owner' => 'field_owner_state',
  ];

  /**
   * {@inheritdoc}
   */
  protected function createRdfEntity(array $values = []): RdfInterface {
    $values += [
      // Prevent notifications for test entities creation.
      'skip_notification' => TRUE,
    ];

    // If the state field is empty, set a default value.
    $state_field = static::STATE_FIELDS[$values['rid']] ?? NULL;
    if (!isset($values[$state_field])) {
      $values[$state_field] = 'published';
    }

    // When the solution's parent collection is not relevant for the test it can
    // be omitted, and we'll assign a fake one.
    if ($values['rid'] === 'solution' && !isset($values['collection'])) {
      $collection = $this->createRdfEntity([
        'rid' => 'collection',
        'label' => $this->randomString(),
      ]);
      $values['collection'] = $collection;
    }

    return $this->contribCreateRdfEntity($values);
  }

}
