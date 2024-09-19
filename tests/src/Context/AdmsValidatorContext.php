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
    $config = \Drupal::config('rdf_sync.settings');
    $graphUri = $config->get('graph_uri');
    /** @var \Drupal\rdf_sync\RdfSyncConnectionInterface $sparqlEndpoint */
    $sparqlEndpoint = \Drupal::service('rdf_sync.connection');
    // Copy the data to a new graph to avoid deleting the original data.
    $sparqlEndpoint->query("ADD <$graphUri> TO GRAPH <http://adms-validation>");
    /** @var \Drupal\adms_validator\AdmsValidatorInterface $validator */
    $validator = \Drupal::service('adms_validator.validator');
    $result = $validator->validateByGraphUri('http://adms-validation');
    $sparqlEndpoint->query('CLEAR GRAPH <http://adms-validation>');
    Assert::assertTrue($result->isSuccessful(), 'The published graph is not ADMS-AP compliant.');
  }

}
