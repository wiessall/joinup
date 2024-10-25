<?php

declare(strict_types=1);

namespace Drupal\joinup_debug\Form;

use Drupal\Component\Utility\Random;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\sparql_entity_storage\Driver\Database\sparql\ConnectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Portal RDF form.
 */
final class SparqlConsoleForm extends FormBase {

  /**
   * The SPARQL endpoint.
   *
   * @var \Drupal\sparql_entity_storage\Driver\Database\sparql\ConnectionInterface $endpoint
   *   The SPARQL endpoint.
   */
  public function __construct(protected ConnectionInterface $endpoint) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('sparql.endpoint'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'joinup_rdf_sparql_query';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    // Add some AJAX buttons that provide quick queries for the user.
    $this->addAdditionalQuickActionQueries($form);

    // A textarea to input the SPARQL query.
    $form['query'] = [
      '#type' => 'textarea',
      '#title' => $this->t('SPARQL query'),
      '#required' => TRUE,
      // Setup ajax parameters so that the `fetch_all_graphs` button can
      // trigger an ajax callback.
      '#prefix' => '<div id="edit-query">',
      '#suffix' => '</div>',
      '#default_value' => $form_state->getValue('query'),
    ];

    $form['actions'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send'),
      '#ajax' => [
        'callback' => '::ajaxSubmit',
        'wrapper' => 'results-container',
      ],
    ];

    // A markup element to display the results.
    $form['results_container'] = [
      '#type' => 'details',
      '#title' => $this->t('Results'),
      '#open' => TRUE,
      '#attributes' => [
        'id' => 'results-container',
      ],
    ];
    $form['results_container']['results'] = [
      '#type' => 'markup',
      '#markup' => '<pre>' . $form_state->getValue('results') . '</pre>',
    ];

    return $form;
  }

  /**
   * Adds some AJAX buttons that provide quick queries for the user.
   *
   * @param array $form
   *   The form.
   */
  protected function addAdditionalQuickActionQueries(array &$form): void {
    // Add an AJAX button that checks the write permission by inserting and
    // deleting a triple.
    $form['check-write-permission'] = [
      '#type' => 'button',
      '#value' => $this->t('Check write permission'),
      '#ajax' => [
        'callback' => '::ajaxCheckWritePermission',
        'wrapper' => 'results-container',
      ],
      '#limit_validation_errors' => [],
    ];

    foreach ($this->getQuickActionQueries() as $buttonName => $query) {
      $form[$buttonName] = [
        '#type' => 'button',
        '#value' => $this->t('Quick query: @buttonName', [
          '@buttonName' => ucfirst(str_replace('-', ' ', $buttonName)),
        ]),
        '#ajax' => [
          'callback' => '::ajaxQuickActionQuery',
          'wrapper' => 'edit-query',
        ],
        '#limit_validation_errors' => [],
        '#attributes' => [
          'data-selector' => $buttonName,
        ],
      ];
    }
  }

  /**
   * Ajax callback to fetch all graphs.
   */
  public function ajaxQuickActionQuery(array &$form, FormStateInterface $form_state): array {
    $buttonName = $form_state->getTriggeringElement()['#attributes']['data-selector'];
    $query = $this->getQuickActionQueries()[$buttonName];
    $form['query']['#value'] = $query;
    return $form['query'];
  }

  /**
   * Ajax callback to test write permissions.
   */
  public function ajaxCheckWritePermission(array &$form, FormStateInterface $form_state): array {
    $random = new Random();
    $graph = 'http://example.com/' . $random->name(10, TRUE);
    $query = "WITH <$graph> INSERT DATA { <http://example.com> <http://example.com> <http://example.com> }";
    try {
      $results = $this->endpoint->query($query);
      $value = $results->dump();
    }
    catch (\Exception $e) {
      $value = $e->getMessage();
    }

    // Delete the triple.
    $query = "CLEAR GRAPH <$graph>";
    try {
      $results = $this->endpoint->query($query);
      $value .= "\n" . $results->dump();
    }
    catch (\Exception $e) {
      $value .= "\n" . $e->getMessage();
    }

    $form['results_container']['results']['#markup'] = '<pre>' . $value . '</pre>';
    return $form['results_container'];
  }

  /**
   * Ajax callback to submit the form.
   */
  public function ajaxSubmit(array &$form, FormStateInterface $formState): array {
    $query = $formState->getValue('query');
    if (empty(trim($query))) {
      $formState->setErrorByName('query', $this->t('The query cannot be empty.'));
    }

    // If the query is valid, we execute it and display the results.
    if (!$formState->getErrors()) {
      $value = '';
      try {
        $results = $this->endpoint->query($query);
      }
      catch (\Exception $e) {
        $value = $e->getMessage();
      }
      $value .= $results ? $results->dump() : '';
      $form['results_container']['results']['#markup'] = '<pre>' . $value . '</pre>';
    }

    return $form['results_container'];
  }

  /**
   * Returns the quick action queries.
   *
   * @return array
   *   The quick action queries.
   */
  protected function getQuickActionQueries(): array {
    return [
      'fetch-graphs' => "SELECT DISTINCT ?g\nWHERE {\n GRAPH ?graph { ?s ?p ?o } .\n BIND (str(?graph) as ?g) \n}",
      'graph-triples-count' => "SELECT COUNT(*)\nFROM <INSERT_GRAPH_HERE>\nWHERE {\n ?s ?p ?o \n} LIMIT 100",
      'properties-of-graph' => "SELECT DISTINCT ?p\nFROM <INSERT_GRAPH_HERE>\nWHERE {\n ?s ?p ?o \n} LIMIT 100",
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {}

}
