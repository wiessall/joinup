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
   * {@inheritdoc}
   */
  protected function createRdfEntity(array $values = []): RdfInterface {
    $values += [
      // Prevent notifications for test entities creation.
      'skip_notification' => TRUE,
    ];
    return $this->contribCreateRdfEntity($values);
  }

}
