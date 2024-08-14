<?php

declare(strict_types=1);

namespace Drupal\joinup_rdf;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationManager;
use Drupal\sparql_entity_storage\Driver\Database\sparql\ConnectionInterface;

/**
 * Implements helper methods related to the requirements.
 */
class RequirementsHelper {

  use StringTranslationTrait;

  /**
   * The SQL connection class for the SPARQL database storage.
   *
   * @var \Drupal\sparql_entity_storage\Driver\Database\sparql\ConnectionInterface
   */
  protected $sparql;

  /**
   * The translation manager service.
   *
   * @var \Drupal\Core\StringTranslation\TranslationManager
   */
  protected $translation;

  /**
   * Constructs a new RequirementsHelper.
   *
   * @param \Drupal\sparql_entity_storage\Driver\Database\sparql\ConnectionInterface $sparql
   *   The SQL connection class for the SPARQL database storage.
   * @param \Drupal\Core\StringTranslation\TranslationManager $translation
   *   The translation manager service.
   */
  public function __construct(ConnectionInterface $sparql, TranslationManager $translation) {
    $this->sparql = $sparql;
    $this->translation = $translation;
  }

  /**
   * Returns a list of error messages for the joinup_rdf requirements.
   *
   * @return array
   *   A list of error messages.
   */
  public function getRequirementErrors(): array {
    $errors = [];

    if ($graphs = $this->getLeftoverFederationGraphs()) {
      $errors[] = [
        '#markup' => $this->t('Leftover graphs were found:'),
        'children' => [
          '#items' => $graphs,
        ],
      ];
    }

    if ($errors) {
      return [
        '#theme' => 'item_list',
        '#items' => $errors,
      ];
    }

    return $errors;
  }

  /**
   * Fetches the number of leftover graphs.
   *
   * @return array
   *   The leftover graphs.
   */
  protected function getLeftoverFederationGraphs(): array {
    $query = <<<QUERY
SELECT DISTINCT ?g WHERE { GRAPH ?g {?s ?p ?o} } ORDER BY ?g
QUERY;

    $graphs = $this->sparql->query($query);
    $errors = [];
    foreach ($graphs as $graph) {
      $uri = $graph->g->getUri();
      if (strpos($uri, 'http://adms-validator/') === 0) {
        $errors[] = $uri;
      }
    }

    return $errors;
  }

}
