<?php

declare(strict_types=1);

namespace Drupal\sparql_endpoint\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
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
  public function buildForm(array $form, FormStateInterface $form_state): array|Response {
    $requestQuery = $this->getRequest()->query->all();
    $hasValidQuery = !empty($requestQuery['query']) && !empty($requestQuery['format']) && in_array($requestQuery['format'], array_keys(self::ALLOWED_FORMATS));
    if ($hasValidQuery && $requestQuery['format'] !== 'text/html') {
      return $this->buildExplicitResponse($requestQuery['query'], $requestQuery['format']);
    }

    $form['query'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Query'),
      '#default_value' => $hasValidQuery ? $requestQuery['query'] : 'SELECT * WHERE { ?s ?p ?o } LIMIT 10',
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
      '#default_value' => $hasValidQuery ? $requestQuery['format'] : 'text/html',
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    if ($hasValidQuery) {
      $results = $this->getResultsFromEndpoint($requestQuery['query'], $requestQuery['format']);
      $form['results'] = [
        '#type' => 'markup',
        '#markup' => '<div class="endpoint-results">' . $results['results'] . '</div>',
      ];
    }
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
    if ($format === 'text/html') {
      $form_state->setRedirect('sparql_endpoint.endpoint', [], [
        'query' => [
          'query' => $query,
          'format' => $format,
        ],
      ]);
    }
    else {
      $form_state->setResponse($this->buildExplicitResponse($query, $format));
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
   * Handle the results for formats other than text/html.
   *
   * @param string $query
   *   The query string.
   * @param string $format
   *   The format string.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A response object with the returned contents.
   */
  protected function buildExplicitResponse(string $query, string $format): Response {
    $results = $this->getResultsFromEndpoint($query, $format);
    if ($results['success'] === FALSE) {
      // Follow the same practice as the endpoint. Regardless of the format, if
      // an error exists, return it as text/plain with the correct error code.
      return new Response($results['results'], $results['code'], [
        'Content-Type' => 'text/plain',
      ]);
    }
    if ($format === 'application/sparql-results+json') {
      return new JsonResponse(json_decode($results['results'], TRUE), $results['code']);
    }

    $headers = [
      'Content-Type' => $format,
    ];
    if ($format === 'text/csv') {
      $headers['Content-Disposition'] = 'attachment; filename="sparql-results.csv"';
    }

    return new Response($results['results'], $results['code'], $headers);
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
      $links = [];
      foreach (self::ALLOWED_FORMATS as $format => $label) {
        $queryLink = Url::fromRoute('sparql_endpoint.endpoint', [], [
          'query' => [
            'query' => $helperQuery['query'],
            'format' => $format,
          ],
        ])->setAbsolute()->toString();
        $links[] = "<a href='$queryLink'>$label</a>";
      }
      $return[] = "<strong>{$helperQuery['title']}: </strong> Run query: " . implode(' | ', $links) . "<br><pre>" . htmlentities($helperQuery['query']) . "</pre>";
    }

    return implode('<br>', $return);
  }

}
