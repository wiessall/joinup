<?php

declare(strict_types=1);

namespace Drupal\joinup_rdf;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationManager;
use Drupal\rdf_sync\RdfSyncConnectionInterface;

/**
 * Implements helper methods related to the requirements.
 *
 * @todo Evaluate in ISAICP-8376 if this is still needed.
 */
class RequirementsHelper {

  use StringTranslationTrait;

  /**
   * Constructs a new RequirementsHelper.
   *
   * @param \Drupal\rdf_sync\RdfSyncConnectionInterface $sparql
   *   The SQL connection class for the SPARQL database storage.
   * @param \Drupal\Core\StringTranslation\TranslationManager $translation
   *   The translation manager service.
   */
  public function __construct(
    protected RdfSyncConnectionInterface $sparql,
    protected TranslationManager $translation,
  ) {}

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
      if (str_starts_with($uri, 'http://adms-validator/')) {
        $errors[] = $uri;
      }
    }

    return $errors;
  }

}
