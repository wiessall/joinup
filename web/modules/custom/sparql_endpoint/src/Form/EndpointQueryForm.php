<?php

declare(strict_types=1);

namespace Drupal\sparql_endpoint\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Provides a Sparql endpoint form.
 */
class EndpointQueryForm extends FormBase {

  /**
   * Constructs a new EndpointQueryForm object.
   *
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   The HTTP client.
   */
  public function __construct(protected ClientInterface $httpClient) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('http_client')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'sparql_endpoint_endpoint_query';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $requestQuery = $this->getRequest()->query->all();
    if (!empty($requestQuery['query']) && isset($requestQuery['format']) && str_starts_with($requestQuery['format'], 'text/')) {
      $default_value = $requestQuery['query'];
      $results = $this->getResultsFromEndpoint($requestQuery['query'], $requestQuery['format']);
      $form_state->set('results', $results['results']);
      $form_state->set('format', $requestQuery['format']);
    }

    $form['query'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Query'),
      '#default_value' => $default_value ?? 'SELECT * WHERE { ?s ?p ?o } LIMIT 10',
      '#required' => TRUE,
      '#cache' => [
        'max-age' => 0,
      ],
    ];

    $form['help'] = [
      '#type' => 'details',
      '#title' => $this->t('Help'),
      '#open' => FALSE,
      '#markup' => $this->getHelpText(),
    ];

    $form['format'] = [
      '#type' => 'select',
      '#title' => $this->t('Format'),
      '#description' => $this->t('Select the format of the response.'),
      '#options' => [
        'text/html' => $this->t('HTML'),
        'text/plain' => $this->t('N-Triples'),
        'text/turtle' => $this->t('Turtle'),
        'json' => $this->t('JSON'),
        'xml' => $this->t('XML'),
        'csv' => $this->t('CSV'),
      ],
      '#default_value' => 'text/html',
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];
    $this->handleResults($form, $form_state);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $query = $form_state->getValue('query');
    $format = $form_state->getValue('format');
    $results = $this->getResultsFromEndpoint($query, $format);

    if ($results['success'] === FALSE) {
      $form_state->set('results', $results['results']);
      $form_state->setRebuild();
      return;
    }

    if (str_starts_with($format, 'text/')) {
      $form_state->set('results', $results['results']);
      $form_state->set('format', $format);
      $form_state->setRebuild();
    }
    elseif ($format === 'csv') {
      // Return a redirect response as a csv file with the results.
      $response = new StreamedResponse();
      $response->headers->set('Content-Type', 'text/csv');
      $response->headers->set('Content-Disposition', 'attachment; filename="results.csv"');
      $response->setCallback(function () use ($results) {
        echo $results['results'];
      });
      $form_state->setResponse($response);
    }
    else {
      // Return a redirect response as a json or xml file with the results.
      $response = new StreamedResponse();
      $response->headers->set('Content-Type', 'application/' . $format);
      $response->headers->set('Content-Disposition', 'attachment; filename="results.' . $format . '"');
      $response->setCallback(function () use ($results) {
        echo $results['results'];
      });
      $form_state->setResponse($response);
    }
  }

  /**
   * Build the query URI.
   *
   * @param string $query
   *   The query.
   * @param string $format
   *   The format.
   *
   * @return string
   *   The query URI.
   */
  protected function buildQueryUri(string $query, string $format): string {
    return $_SERVER['SPARQL_PUBLIC_ENDPOINT'] . '?default-graph-uri=&query=' . urlencode($query) . '&format=' . urlencode($format) . '&should-sponge=&timeout=0&signal_void=on';
  }

  /**
   * Get the results from the endpoint.
   *
   * @param string $query
   *   The query.
   * @param string $format
   *   The format.
   *
   * @return array
   *   An array containing the keys 'success' and 'results'.
   */
  protected function getResultsFromEndpoint(string $query, string $format): array {
    $url = $this->buildQueryUri($query, $format);

    try {
      $endpointResponse = $this->httpClient->get($url);
    }
    catch (\Exception $e) {
      return [
        'success' => FALSE,
        'results' => $e->getResponse()->getBody()->getContents(),
      ];
    }

    return [
      'success' => TRUE,
      'results' => $endpointResponse->getBody()->getContents(),
    ];
  }

  /**
   * Handle the results.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function handleResults(array &$form, FormStateInterface $form_state): void {
    if (empty($form_state->get('results'))) {
      return;
    }

    $form['results'] = [
      '#type' => 'markup',
      '#markup' => $form_state->get('results'),
      '#prefix' => $form_state->get('format') === 'text/html' ? '<div class="query-results">' : '<div class="query-results"><pre>',
      '#suffix' => $form_state->get('format') === 'text/html' ? '</div>' : '</pre></div>',
      '#cache' => [
        'max-age' => 0,
      ],
    ];
  }

  /**
   * Returns the help string for the query box.
   *
   * @return string
   *   The help text containing sample queries and run links.
   */
  protected function getHelpText(): string {
    $helperQueries = [
      [
        'title' => 'Fetch all data containing the keyword "Berlin"',
        'query' => 'SELECT ?s ?p ?o WHERE { ?s ?p ?o FILTER regex(?o, "Berlin", "i") } ORDER BY ?s LIMIT 10 OFFSET 10',
      ],
      [
        'title' => 'Fetch IDs of collections',
        'query' => 'SELECT DISTINCT(?s) WHERE { ?s a <http://www.w3.org/ns/dcat#Catalog> } LIMIT 10',
      ],
      [
        'title' => 'Fetch IDs of solutions and releases',
        'query' => 'SELECT DISTINCT(?s) WHERE { ?s a <http://www.w3.org/ns/dcat#Dataset> } LIMIT 10',
      ],
    ];

    $return = [];
    foreach ($helperQueries as $helperQuery) {
      $queryLink = Url::fromRoute('sparql_endpoint.endpoint', [], [
        'query' => [
          'query' => $helperQuery['query'],
          'format' => 'text/html',
        ],
      ])->setAbsolute()->toString();
      $return[] = "<strong>{$helperQuery['title']}: </strong><a href='$queryLink'>Run query</a><br><pre>" . htmlentities($helperQuery['query']) . "</pre>";
    }

    return implode('<br>', $return);
  }

}
