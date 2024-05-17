<?php

declare(strict_types=1);

namespace Drupal\sparql_endpoint\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provides a Sparql endpoint form.
 */
class EndpointQueryForm extends FormBase {

  const ALLOWED_FORMATS = [
    'text/html' => 'HTML',
    'text/plain' => 'N-Triples',
    'text/turtle' => 'Turtle',
    'application/rdf+xml' => 'RDF/XML',
    'application/sparql-results+json' => 'JSON',
    'application/sparql-results+xml' => 'XML',
    'text/csv' => 'CSV',
  ];

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
      $format = $requestQuery['format'];
      if (in_array($format, array_keys(self::ALLOWED_FORMATS))) {
        $default_value = $requestQuery['query'];
        $results = $this->getResultsFromEndpoint($requestQuery['query'], $requestQuery['format']);
        $form_state->set('results', $results['results']);
        $form_state->set('format', $requestQuery['format']);
      }
      else {
        $this->messenger()->addError($this->t('Invalid format provided: @format', ['@format' => $format]));
        // Set to default so that the proper option is selected.
        $format = 'text/html';
      }
    }

    $form['query'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Query'),
      '#default_value' => $default_value ?? 'SELECT * WHERE { ?s ?p ?o } LIMIT 10',
      '#required' => TRUE,
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
      '#options' => self::ALLOWED_FORMATS,
      '#default_value' => $format ?? 'text/html',
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

    switch ($format) {
      case 'text/html':
        $form_state->set('results', $results['results']);
        $form_state->set('format', $format);
        $form_state->setRebuild();
        break;
      case 'application/sparql-results+json':
        $response = new JsonResponse(json_decode($results['results'], TRUE));
        $response->setStatusCode($results['code']);
        $form_state->setResponse($response);
        break;

      case 'text/csv':
      case 'text/plain':
      case 'text/turtle':
      case 'application/rdf+xml':
      case 'application/sparql-results+xml':
        $headers = [
          'Content-Type' => $format,
        ];
        if ($format === 'text/csv') {
          $headers['Content-Disposition'] = 'attachment; filename="sparql-results.csv"';
        }

        $response = new Response($results['results'], $results['code'], $headers);
        $form_state->setResponse($response);
        break;

      default:
        $response = new RedirectResponse(Url::fromRoute('sparql_endpoint.endpoint', [], [
          'query' => [
            'query' => $query,
            'format' => $format,
          ],
        ])->toString());
        $response->setStatusCode($results['code']);
        $form_state->setResponse($response);
        break;

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
    // @todo It would be fine to have our own query validator to prevent
    //   sending invalid queries to the endpoint.
    $url = $this->buildQueryUri($query, $format);

    try {
      $endpointResponse = $this->httpClient->get($url);
    }
    catch (\Exception $e) {
      return [
        'success' => FALSE,
        'results' => $e->getResponse()->getBody()->getContents(),
        'code' => $e->getCode(),
      ];
    }

    return [
      'success' => TRUE,
      'results' => $endpointResponse->getBody()->getContents(),
      'code' => $endpointResponse->getStatusCode(),
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
