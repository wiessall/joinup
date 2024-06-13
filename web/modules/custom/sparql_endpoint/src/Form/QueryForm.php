<?php

declare(strict_types=1);

namespace Drupal\sparql_endpoint\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\sparql_endpoint\SparqlFormatManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Sparql query form.
 */
class QueryForm extends FormBase {

  public function __construct(
    protected SparqlFormatManager $sparqlFormatManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('plugin.manager.sparql_endpoint.sparql_format')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'sparql_endpoint_query';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $requestQuery = $this->getRequest()->query->all();

    $form['query'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Query'),
      '#default_value' => $requestQuery['query'] ?? 'SELECT * WHERE { ?s ?p ?o } LIMIT 10',
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
      '#options' => $this->getIdOptions(),
      '#default_value' => $requestQuery['format'] ?? 'html',
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

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
    $form_state->setRedirect('sparql_endpoint.query', [], [
      'query' => [
        'query' => $form_state->getValue('query'),
        'format' => $form_state->getValue('format'),
      ],
    ]);
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
      foreach ($this->getIdOptions() as $format => $label) {
        $queryLink = Url::fromRoute('sparql_endpoint.query', [], [
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

  /**
   * Retrieves the available ID options from the SparqlFormatManager.
   *
   * @return array
   *   An array of ID options.
   */
  protected function getIdOptions(): array {
    $options = [];

    $definitions = $this->sparqlFormatManager->getDefinitions();

    // Sort plugins by weight.
    uasort($definitions, function ($a, $b) {
      return $a['weight'] <=> $b['weight'];
    });

    foreach ($definitions as $definition) {
      $options[$definition['id']] = $definition['label'];
    }

    return $options;
  }

}
