<?php

declare(strict_types=1);

namespace Drupal\voc_comparer\Form;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\joinup_rdf\VocabularyFixturesHelperInterface;
use Drupal\rdf_sync\RdfSyncConnectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Voc comparer form.
 */
class EiraVocabularyVersionCompareForm extends FormBase {

  /**
   * Constructs a new VocabularyVersionCompareForm.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Cache\CacheBackendInterface $databaseCache
   *   The cache backend service.
   * @param \Drupal\joinup_rdf\VocabularyFixturesHelperInterface $fixtureImporter
   *   The vocabulary fixtures helper service.
   * @param \Drupal\rdf_sync\RdfSyncConnectionInterface $sparql
   *   The SPARQL database connection.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected CacheBackendInterface $databaseCache,
    protected VocabularyFixturesHelperInterface $fixtureImporter,
    protected RdfSyncConnectionInterface $sparql,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('cache.default'),
      $container->get('joinup_rdf.vocabulary_fixtures.helper'),
      $container->get('rdf_sync.connection')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'voc_comparer_vocabulary_version_compare';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['help_text'] = [
      '#type' => 'item',
      '#markup' => $this->t('Upload the new file below and select which vocabulary to test against.'),
    ];

    $form['file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('File'),
      '#description' => $this->t('Upload the new file to compare.'),
      '#upload_location' => 'public://voc_comparer',
      '#upload_validators' => [
        'FileExtension' => ['extensions' => 'rdf'],
      ],
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    if ($this->databaseCache->get('joinup_vocabularies.compare_results')) {
      $form['results'] = [
        '#type' => 'container',
      ];

      $form['results']['new'] = $this->newTermsToRenderArray();
      $form['results']['missing'] = $this->removedUsedTermsToRenderArray();
      $form['results']['changed'] = $this->newSiblingsOfRemovedUsedTermsToRenderArray();
      $form['results']['new_deprecated'] = $this->newDeprecatedTermsToRenderArray();
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $file = $form_state->getValue('file');
    $file_uri = $this->entityTypeManager->getStorage('file')->load($file[0])->getFileUri();
    $batch_builder = (new BatchBuilder())
      ->setTitle($this->t('Comparing vocabulary versions'))
      ->setFinishCallback([$this, 'batchFinished'])
      ->setInitMessage($this->t('Comparing vocabulary versions.'))
      ->addOperation([$this, 'setupVocabulary'], [$file[0], $file_uri])
      ->addOperation([$this, 'calculateNewTerms'])
      ->addOperation([$this, 'calculateRemovedUsedTerms'])
      ->addOperation([$this, 'loadNewSiblingsOfRemovedUsedTerms'])
      ->addOperation([$this, 'calculateNewDeprecatedTerms'])
      ->addOperation([$this, 'cleanupBatch']);
    batch_set($batch_builder->toArray());
  }

  /**
   * Imports the vocabulary from the uploaded file.
   *
   * @param string $file_id
   *   The ID of the uploaded file entity.
   * @param string $file_uri
   *   The path of the RDF file to import.
   * @param array $context
   *   The batch context.
   *
   * @return string
   *   The result message.
   */
  public function setupVocabulary(string $file_id, string $file_uri, array &$context): string {
    $graph = "http://example.com/voc-comparer/eira";
    $this->fixtureImporter->importFixtureToGraph($file_uri, $graph);
    $context['results']['compare']['new_graph'] = $graph;
    $context['results']['compare']['file_id'] = $file_id;
    $context['results']['compare']['file_uri'] = $file_uri;

    return "Imported the new version of the EIRA vocabulary from file {$file_uri}";
  }

  /**
   * Calculates the new terms and categories in the new version.
   *
   * @param array $context
   *   The batch context.
   *
   * @return string
   *   The result message.
   */
  public function calculateNewTerms(array &$context): string {
    $new_graph = $context['results']['compare']['new_graph'];

    // Load new terms and categories of the new version.
    $query = <<<QUERY
SELECT ?term ?type ?label ?category ?category_type ?category_label
FROM <{$new_graph}>
FROM <http://data>
WHERE {
  GRAPH <{$new_graph}> {
    ?term a skos:Concept .
    ?term a ?type .
    ?term skos:prefLabel ?label .
    OPTIONAL {
      {
        {
          ?term skos:broader ?category .
          ?category a ?category_type ;
          skos:prefLabel ?category_label
        }
        UNION
        {
          ?category a skos:Collection ;
            skos:member ?term ;
            a ?category_type ;
            skos:prefLabel ?category_label
        }
      }
    }
  }
  FILTER NOT EXISTS {
    GRAPH <http://data> {
      ?term a skos:Concept .
      ?term skos:topConceptOf <http://data.europa.eu/dr8>
    }
  }
}
QUERY;

    foreach ($this->sparql->query($query)->getArrayCopy() as $row) {
      $context['results']['compare']['new'][$row->term->getUri()] = [
        'id' => $row->term->getUri(),
        'label' => $row->label->getValue(),
        'category_id' => isset($row->category) ? $row->category->getUri() : '',
        'category_label' => isset($row->category_label) ? $row->category_label->getValue() : '',
      ];
    }

    return 'Done calculating new terms and categories.';
  }

  /**
   * Prepares a render array of the new terms.
   *
   * @return array
   *   The render array.
   */
  protected function newTermsToRenderArray(): array {
    $cache_data = $this->databaseCache->get('joinup_vocabularies.compare_results');
    $new_terms = $cache_data->data['new'];
    // Sort the array according to the category label alphabetically and then
    // the term label alphabetically.
    uasort($new_terms, function ($a, $b) {
      if ($a['category_label'] === $b['category_label']) {
        return strnatcasecmp($a['label'], $b['label']);
      }
      return strnatcasecmp($a['category_label'], $b['category_label']);
    });

    $header = [
      $this->t('Term ID'),
      $this->t('Term label'),
      $this->t('Category ID'),
      $this->t('Category label'),
    ];

    $rows = [];
    foreach ($new_terms as $term) {
      $rows[] = [
        $term['id'],
        $term['label'],
        $term['category_id'],
        $term['category_label'],
      ];
    }

    return [
      '#type' => 'details',
      '#title' => $this->t('New terms'),
      'table' => [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
      ],
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    ];
  }

  /**
   * Calculates the removed deprecated terms and their usages.
   *
   * @param array $context
   *   The batch context.
   *
   * @return string
   *   The result message.
   */
  public function calculateRemovedUsedTerms(array &$context): string {
    $new_graph = $context['results']['compare']['new_graph'];

    // Load usages of the removed terms.
    $query = <<<QUERY
SELECT ?term ?label ?usage ?usage_label ?is_deprecated
FROM <{$new_graph}>
FROM <http://data>
FROM <http://joinup.eu/solution/published>
FROM <http://joinup.eu/solution/draft>
WHERE {
  GRAPH <http://data> {
    ?term a skos:Concept .
    ?term skos:prefLabel ?label .
    ?term skos:topConceptOf <http://data.europa.eu/dr8> .
    OPTIONAL {
      ?term skos:deprecated ?is_deprecated .
    }
  }
  FILTER NOT EXISTS {
    GRAPH <{$new_graph}> {
      ?term a skos:Concept .
    }
  }
  ?usage a <http://www.w3.org/ns/dcat#Dataset> ;
    <http://purl.org/dc/terms/type> ?term ;
    <http://purl.org/dc/terms/title> ?usage_label .
}
QUERY;

    foreach ($this->sparql->query($query)->getArrayCopy() as $row) {
      if (!isset($context['results']['compare']['missing'][$row->term->getUri()])) {
        $context['results']['compare']['missing'][$row->term->getUri()] = [
          'id' => $row->term->getUri(),
          'label' => $row->label->getValue(),
          'is_deprecated' => isset($row->is_deprecated) ? $row->is_deprecated->getValue() : FALSE,
          'usages' => [],
        ];
      }
      $context['results']['compare']['missing'][$row->term->getUri()]['usages'][$row->usage->getUri()] = [
        'id' => $row->usage->getUri(),
        'label' => $row->usage_label->getValue(),
      ];
    }

    return 'Finished fetching of missing used terms.';
  }

  /**
   * Prepares a render array of the deprecated terms and their usages.
   *
   * @return array
   *   The render array.
   */
  protected function removedUsedTermsToRenderArray(): array {
    $cache_data = $this->databaseCache->get('joinup_vocabularies.compare_results');
    if (!isset($cache_data->data['missing'])) {
      return [];
    }
    $removed_terms = $cache_data->data['missing'];

    $header = [
      $this->t('Term ID'),
      $this->t('Term label'),
      $this->t('Deprecated'),
      $this->t('Usage ID'),
      $this->t('Usage label'),
    ];

    $rows = [];
    foreach ($removed_terms as $term) {
      foreach ($term['usages'] as $usage) {
        $rows[] = [
          $term['id'],
          $term['label'],
          $term['is_deprecated'] ? $this->t('Yes') : $this->t('No'),
          $usage['id'],
          $usage['label'],
        ];
      }
    }

    return [
      '#type' => 'details',
      '#title' => $this->t('Removed terms'),
      'table' => [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
      ],
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    ];
  }

  /**
   * Loads the new siblings of the removed terms that are still used.
   *
   * The idea is that often, a term that is removed is replaced by a  similar
   * one or breaks down to multiple subterms.
   *
   * @param array $context
   *   The batch context.
   *
   * @return string
   *   The result message.
   */
  public function loadNewSiblingsOfRemovedUsedTerms(array &$context): string {
    $new_graph = $context['results']['compare']['new_graph'];

    if (!isset($context['results']['compare']['missing'])) {
      return 'No missing terms to fetch siblings for.';
    }

    foreach ($context['results']['compare']['missing'] as $term_id => $term) {
      $query = <<<QUERY
SELECT ?sibling ?sibling_label
FROM <{$new_graph}>
FROM <http://data>
WHERE {
    GRAPH <{$new_graph}> {
      ?sibling a skos:Concept ;
        skos:prefLabel ?sibling_label .
        ?category a skos:Collection ;
          skos:member ?sibling ;
          a ?category_type ;
          skos:prefLabel ?category_label
    }
    FILTER EXISTS {
      GRAPH <http://data> {
        ?category skos:member <{$term_id}> .
        ?term skos:topConceptOf <http://data.europa.eu/dr8> .
      }
    }
}
QUERY;

      foreach ($this->sparql->query($query)->getArrayCopy() as $row) {
        $context['results']['compare']['missing'][$term_id]['siblings'][$row->sibling->getUri()] = [
          'id' => $row->sibling->getUri(),
          'label' => $row->sibling_label->getValue(),
        ];
      }
    }

    return 'Finished fetching siblings of missing terms.';
  }

  /**
   * Prepares a render array of the new siblings of the removed terms.
   *
   * @return array
   *   The render array.
   */
  protected function newSiblingsOfRemovedUsedTermsToRenderArray(): array {
    $cache_data = $this->databaseCache->get('joinup_vocabularies.compare_results');
    if (!isset($cache_data->data['missing'])) {
      return [];
    }
    $removed_terms = $cache_data->data['missing'];

    $header = [
      $this->t('Term ID'),
      $this->t('Term label'),
      $this->t('Deprecated'),
      $this->t('Sibling ID'),
      $this->t('Sibling label'),
    ];

    $rows = [];
    foreach ($removed_terms as $term) {
      if (empty($term['siblings'])) {
        continue;
      }

      $first_row = array_slice($term['siblings'], 0, 1);
      $first_row = reset($first_row);
      $rows[] = [
        $term['id'],
        $term['label'],
        $term['is_deprecated'] ? $this->t('Yes') : $this->t('No'),
        $first_row['id'],
        $first_row['label'],
      ];

      foreach ($term['siblings'] as $sibling) {
        $rows[] = [
          '',
          '',
          '',
          $sibling['id'],
          $sibling['label'],
        ];
      }
    }

    return [
      '#type' => 'details',
      '#title' => $this->t('New siblings of removed terms'),
      'table' => [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
      ],
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    ];
  }

  /**
   * Calculates the new deprecated terms.
   *
   * @param array $context
   *   The batch context.
   *
   * @return string
   *   The result message.
   */
  public function calculateNewDeprecatedTerms(array &$context): string {
    $new_graph = $context['results']['compare']['new_graph'];

    // Load new deprecated terms of the new version and their usages.
    $query = <<<QUERY
SELECT ?term ?label ?usage
FROM <{$new_graph}>
FROM <http://data>
FROM <http://joinup.eu/solution/published>
FROM <http://joinup.eu/solution/draft>
WHERE {
  GRAPH <{$new_graph}> {
    ?term a skos:Concept .
    ?term skos:prefLabel ?label .
    ?term skos:deprecated "true"^^xsd:boolean .
  }
  FILTER NOT EXISTS {
    GRAPH <http://data> {
      ?term a skos:Concept .
      ?term skos:topConceptOf <http://data.europa.eu/dr8> .
      ?term skos:deprecated "true"^^xsd:boolean .
    }
  }
  OPTIONAL {
    ?usage a <http://www.w3.org/ns/dcat#Dataset> ;
      <http://purl.org/dc/terms/type> ?term ;
      <http://purl.org/dc/terms/title> ?usage_label .
  }
}
QUERY;
    foreach ($this->sparql->query($query)->getArrayCopy() as $row) {
      $context['results']['compare']['deprecated_new'][$row->term->getUri()] = [
        'id' => $row->term->getUri(),
        'label' => $row->label->getValue(),
      ];
    }

    return 'Finished calculating new deprecated terms and usages.';
  }

  /**
   * Prepares a render array of the new deprecated terms and their usages.
   *
   * @return array
   *   The render array.
   */
  protected function newDeprecatedTermsToRenderArray(): array {
    $cache_data = $this->databaseCache->get('joinup_vocabularies.compare_results');
    if (!isset($cache_data->data['deprecated_new'])) {
      return [];
    }
    $deprecated_terms = $cache_data->data['deprecated_new'];

    $header = [
      $this->t('Term ID'),
      $this->t('Term label'),
    ];

    $rows = [];
    foreach ($deprecated_terms as $term) {
      $rows[] = [
        $term['id'],
        $term['label'],
      ];
    }

    return [
      '#type' => 'details',
      '#title' => $this->t('New deprecated terms'),
      'table' => [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
      ],
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    ];
  }

  /**
   * Cleans up the graph and file used for the batch.
   *
   * @param array $context
   *   The batch context.
   */
  public function cleanupBatch(array &$context): void {
    $file_id = $context['results']['compare']['file_id'];
    $this->entityTypeManager->getStorage('file')->load($file_id)->delete();
    $this->sparql->getSparqlClient()->clear($context['results']['compare']['new_graph']);
  }

  /**
   * Presents the results and finishes the batch.
   *
   * @param bool $success
   *   The result of the batch.
   * @param array $results
   *   The results of the batch.
   * @param array $operations
   *   The operations of the batch.
   */
  public function batchFinished(bool $success, array $results, array $operations): void {
    if ($success) {
      $this->databaseCache->set('joinup_vocabularies.compare_results', $results['compare'], time() + 3600);
      $this->messenger()->addStatus($this->t('Finished with success.'));
    }
    else {
      $this->messenger()->addError($this->t('Finished with an error.'));
    }
  }

}
