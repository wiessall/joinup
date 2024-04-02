<?php

declare(strict_types=1);

namespace Drupal\owner\Entity;

use Drupal\joinup_bundle_class\JoinupBundleClassFieldAccessTrait;
use Drupal\joinup_workflow\EntityWorkflowStateTrait;
use Drupal\rdf_entity\Entity\Rdf;
use Drupal\sparql_entity_storage\Entity\Query\Sparql\SparqlArg;

/**
 * Bundle class for the 'owner' bundle.
 */
class Owner extends Rdf implements OwnerInterface {

  use EntityWorkflowStateTrait;
  use JoinupBundleClassFieldAccessTrait;

  /**
   * {@inheritdoc}
   */
  public function getWorkflowStateFieldName(): string {
    return 'field_owner_state';
  }

  /**
   * {@inheritdoc}
   */
  public function isReferencedByGroups(): bool {
    if (empty($this->id())) {
      return FALSE;
    }

    $sparql_endpoint = \Drupal::getContainer()->get('sparql.endpoint');
    return $sparql_endpoint->query("ASK { ?s ?p " . SparqlArg::uri($this->id()) . " }")->isTrue();
  }

  /**
   * {@inheritdoc}
   */
  public function getReferencingGroups(): array {
    if (empty($this->id())) {
      return [];
    }

    $query = \Drupal::entityQuery('rdf_entity')->accessCheck(FALSE);
    $result = $query
      ->condition('rid', 'collection')
      ->condition('field_ar_owner', $this->id())
      ->execute();

    return !empty($result) ? Rdf::loadMultiple($result) : [];
  }

}
