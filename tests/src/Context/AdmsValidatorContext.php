<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Drupal\DrupalExtension\Context\RawDrupalContext;
use PHPUnit\Framework\Assert;

/**
 * Behat step definitions to test ADMS-AP validation.
 */
class AdmsValidatorContext extends RawDrupalContext {

  /**
   * Asserts that entities in the published graph are ADMS-AP compliant.
   *
   * @Then the ADMS-AP data of the published entities in Joinup is valid
   */
  public function assertValidPublishedGraph(): void {
    /** @var \Drupal\rdf_sync\RdfSyncConnectionInterface $sparql_endpoint */
    $sparql_endpoint = \Drupal::service('rdf_sync.connection');
    /** @var \Drupal\adms_validator\AdmsValidatorInterface $validator */
    $validator = \Drupal::service('adms_validator.validator');
    $result = $validator->validateByGraphUri('http://data');
    $sparql_endpoint->query('CLEAR GRAPH <http://data>');
    Assert::assertTrue($result->isSuccessful(), 'The published graph is not ADMS-AP compliant.');
  }

}
