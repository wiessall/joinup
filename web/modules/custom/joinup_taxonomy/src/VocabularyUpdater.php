<?php

declare(strict_types = 1);

namespace Drupal\joinup_taxonomy;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\rdf_sync\RdfSyncMapper;
use Drupal\text\Plugin\Field\FieldType\TextItemBase;
use EasyRdf\Graph;
use EasyRdf\RdfNamespace;
use EasyRdf\Resource;

/**
 * Used to update vocabularies.
 *
 * Usage:
 * @code
 * $graph1 = new Graph();
 * $grap1->parseFile('...);
 * $graph2 = $graphStore->get('http://example.com');
 * \Drupal::service('joinup_taxonomy.vocabulary_updater')
 *   ->registerGraph('http://graph1', $graph1)
 *   ->registerGraph('http://graph2', $graph2)
 *   ->registerVocabulary('type', 'http://vocab/type', 'http://graph1')
 *   ->registerVocabulary('other_vocab', 'http://vocab/other', 'http://graph2')
 *   ->registerVocabulary('third_vocab', 'http://vocab/3rd', 'http://graph2')
 *   ->update();
 * @encode
 * This will insert/update terms from the two graphs in the registered vocabs.
 *
 * You can pass an initial list of relations between term URIs and TIDs to help
 * resolve the dependencies between terms being parsed with other terms, which
 * are not part of the process:
 * @code
 * $uriToTid = [
 *   'http://term/1' => 8765,
 *   'http://term/2' => 8767,
 * ];
 * \Drupal::service('joinup_taxonomy.vocabulary_updater')
 *   ->setUriToTid($uriToTid)
 *   ->registerGraph('http://graph1', $graph1)
 *   ->registerVocabulary('type', 'http://vocab/type', 'http://graph1')
 *   ->update();
 * @endcode
 * Terms from 'type' vocab, having references to 'http://term/1' or
 * 'http://term/2', which are not in the current graph are resolved correctly.
 *
 * Note that this service is a *stateful service*, because it persists internal
 * variables, but each call to get the service from container will instantiate a
 * new instance, making it safe to use.
 */
class VocabularyUpdater {

  use VocabularyGraphAlterersTrait;

  /**
   * Registered graphs.
   *
   * @var \EasyRdf\Graph[]
   */
  private array $graphs = [];

  /**
   * Registered vocabularies.
   *
   * @var array[]
   */
  private array $vocabs = [];

  /**
   * Associative array with the relation between the term URI and TID.
   *
   * @var int[]
   */
  private array $uriToTid = [];

  /**
   * Empty result record.
   *
   * @const int[]
   */
  private const RESULT = ['inserted' => 0, 'updated' => 0, 'deleted' => 0];

  /**
   * Constructs a new service instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $fieldManager
   *   The entity field manager service.
   * @param \Drupal\Core\Field\FieldTypePluginManagerInterface $fieldTypeManager
   *   The field type plugin manager service.
   * @param \Drupal\rdf_sync\RdfSyncMapper $mapper
   *   The RDF sync mapper service.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityFieldManagerInterface $fieldManager,
    protected FieldTypePluginManagerInterface $fieldTypeManager,
    protected RdfSyncMapper $mapper,
    protected Connection $database,
  ) {}

  /**
   * Registers a graph to be processed into taxonomy terns.
   *
   * @param string $graphUri
   *   The graph URI.
   * @param \EasyRdf\Graph $graph
   *   The graph object.
   * @param array $callbacks
   *   (optional) List of callbacks able to alter the graph before its data is
   *   extracted into terms. Each callback takes two arguments, in this order:
   *   - The vocabulary ID.
   *   - The graph being altered.
   *
   * @return $this
   */
  public function registerGraph(string $graphUri, Graph $graph, array $callbacks = []): self {
    $this->graphs[$graphUri] = [
      'graph' => $graph,
      'callbacks' => $callbacks,
    ];
    return $this;
  }

  /**
   * Registers a vocabulary to import data into.
   *
   * @param string $vid
   *   The vocabulary ID.
   * @param string $rdfType
   *   The RDF type.
   * @param string $graphUri
   *   The graph URI.
   *
   * @return $this
   */
  public function registerVocabulary(string $vid, string $rdfType, string $graphUri): self {
    $this->vocabs[$vid] = ['type' => $rdfType, 'graph_uri' => $graphUri];
    return $this;
  }

  /**
   * Updates the registered vocabularies terms with data from registered graphs.
   *
   * @return array[]
   *   Associative array keyed by vocabulary ID having. Values are arrays with
   *   the number if inserted, updated and deleted terms.
   */
  public function update(): array {
    if (empty($this->vocabs)) {
      throw new \InvalidArgumentException('No registered vocabularies. Register with \Drupal::service("joinup_taxonomy.vocabulary_updater")->registerVocabulary()');
    }
    if (empty($this->graphs)) {
      throw new \InvalidArgumentException('No registered graphs. Register with \Drupal::service("joinup_taxonomy.vocabulary_updater")->registerGraph()');
    }

    $results = [];
    foreach ($this->graphs as $graphUri => $graph) {
      if ($vids = $this->getVidsForGraph($graphUri)) {
        $results += $this->updateFromGraph($vids, $graph['graph'], $graph['callbacks']);
      }
    }

    // Reset the registered vocabularies and graphs.
    $this->vocabs = [];
    $this->graphs = [];

    return $results;
  }

  /**
   * Sets an initial list of URI to TID relations.
   *
   * This is useful when processed terms have dependencies to other terms, not
   * being currently processed and helps to resolve such dependencies. Note that
   * the cached value (`$this->uriToTid`) increases during the process.
   *
   * @param array $uriToTid
   *   Associative array keyed by term URI and has the term ID as values.
   *
   * @return $this
   */
  public function setUriToTid(array $uriToTid): self {
    $this->uriToTid = array_map('intval', $uriToTid);
    return $this;
  }

  /**
   * Returns the current list of term URIs and their corresponding TID.
   *
   * @return int[]
   *   Associative array keyed by term URI and has the term ID as values.
   */
  public function getUriToTid(): array {
    return $this->uriToTid;
  }

  /**
   * Processes one graph.
   *
   * @param array $vids
   *   Associative array keyed by vocabulary ID and having the vocabulary RDF
   *   type as values.
   * @param \EasyRdf\Graph $graph
   *   The graph to be processed.
   * @param array $callbacks
   *   (optional) List of callbacks able to alter the graph before its data is
   *   extracted into terms. Each callback takes two arguments, in this order:
   *   - The vocabulary ID.
   *   - The graph being altered.
   *
   * @return array[]
   *   Associative array keyed by vocabulary ID having. Values are arrays with
   *   the number if inserted, updated and deleted terms.
   */
  protected function updateFromGraph(array $vids, Graph $graph, array $callbacks = []): array {
    /** @var \Drupal\taxonomy\VocabularyStorageInterface $vocabularyStorage */
    $vocabularyStorage = $this->entityTypeManager->getStorage('taxonomy_vocabulary');
    /** @var \Drupal\taxonomy\TermStorageInterface $termStorage */
    $termStorage = $this->entityTypeManager->getStorage('taxonomy_term');

    $results = [];
    foreach ($vids as $vid => $rdfType) {
      $results[$vid] = self::RESULT;

      /** @var \Drupal\taxonomy\VocabularyInterface $vocabulary */
      $vocabulary = $vocabularyStorage->load($vid);
      $isLockedVocabulary = $vocabulary->getThirdPartySetting('joinup_taxonomy', 'locked', FALSE);

      // Callers might pass a list of callbacks to process the graph before data
      // is extracted and injected in taxonomy terms.
      foreach ($callbacks as $callback) {
        if (is_callable($callback)) {
          $callback($vid, $graph);
        }
      }

      // Extract terms from graph.
      $terms = $this->getTerms($vid, $rdfType, $graph);

      foreach ($terms as $term) {
        if ($existingTerms = $termStorage->loadByProperties(['uri' => $term['uri']])) {
          // Delete if already exists.
          reset($existingTerms)->delete();
          $results[$vid]['updated']++;
        }
        else {
          $results[$vid]['inserted']++;
        }
        $termStorage->create($term)->save();
      }

      if ($isLockedVocabulary) {
        // Locked vocabularies: remove existing terms missing from the graph.
        $processed = array_column($terms, 'uri');
        $tidsToRemove = $termStorage->getQuery()
          ->accessCheck(FALSE)
          ->condition('vid', $vid)
          ->condition('uri', $processed, 'NOT IN')
          ->execute();
        if ($tidsToRemove) {
          $termStorage->delete($termStorage->loadMultiple($tidsToRemove));
          $results[$vid]['deleted'] += count($tidsToRemove);
        }
      }
      $results[$vid] = array_filter($results[$vid]);
    }
    return $results;
  }

  /**
   * Processes the graph and return a list of terms.
   *
   * @param string $vid
   *   The RDF graph.
   * @param string $rdfType
   *   The RDF type.
   * @param \EasyRdf\Graph $graph
   *   The batch context passed by reference.
   *
   * @return array
   *   A list of terms as arrays. Each array is suitable to be passed to the
   *   Term::create() method.
   */
  private function getTerms(string $vid, string $rdfType, Graph $graph): array {
    $fieldStorageDefinitions = $this->fieldManager->getFieldStorageDefinitions('taxonomy_term');
    $fieldDefinitions = $this->fieldManager->getFieldDefinitions('taxonomy_term', $vid);

    $terms = [];
    // For performance reasons, we assign TIDs to terms before they are saved,
    // so that we can compute the entity reference fields between terms (parent,
    // related, etc.) without needing successive entity saves. Getting and using
    // the next auto-increment is not safe in 99.99% of the cases. However, here
    // we strongly assume that no parallel process is underway as this Drush
    // command is used in very rarely and only by developers, only when they
    // re-create the taxonomy terms MySQL dump.
    // @todo Use a directed acyclic graph manipulator to reorder $terms.
    // @see \Drupal\Component\Graph\Graph
    $tid = $this->database->query("SELECT MAX(tid) FROM {taxonomy_term_data}")->fetchField() + 1;
    $revision_id = $this->database->query("SELECT MAX(revision_id) FROM {taxonomy_term_data}")->fetchField() + 1;
    foreach ($graph->resources() as $resource) {
      if (!$this->isTerm($resource, $rdfType)) {
        continue;
      }

      $references = [];
      $term = [
        'tid' => $tid++,
        'revision_id' => $revision_id++,
        'vid' => $vid,
        'uri' => $resource->getUri(),
      ];

      // Register the URI > TID mapping (used to resolve references).
      $this->uriToTid[$resource->getUri()] = (int) $term['tid'];

      $mappings = $this->mapper->getMappings('taxonomy_term', $vid);
      // Already added.
      unset($mappings['vid']);
      foreach ($mappings as $fieldName => $columns) {
        /** @var \Drupal\rdf_sync\Model\ColumnMapping $mapping */
        foreach ($columns as $columnName => $mapping) {
          $fieldType = $fieldStorageDefinitions[$fieldName]->getType();
          $fieldTypePlugin = $this->fieldTypeManager->getDefinition($fieldType);

          $isTermReference = FALSE;
          if (is_a($fieldTypePlugin['class'], EntityReferenceItem::class, TRUE)) {
            // Check if this field is an entity reference to another term.
            $fieldDefinition = $fieldDefinitions[$fieldName];
            if ($fieldDefinition->getSetting('target_type') === 'taxonomy_term') {
              $handlerSettings = $fieldDefinition->getSetting('handler_settings');
              // NULL means "all bundles".
              $isTermReference = $handlerSettings['target_bundles'] === NULL || !empty($handlerSettings['target_bundles']);
            }
          }

          $isFormattedText = is_a($fieldTypePlugin['class'], TextItemBase::class, TRUE);
          // Change 'processed' to 'value' on data import.
          $columnName = $isFormattedText && $columnName === 'processed' ? 'value' : $columnName;

          $property = RdfNamespace::shorten($mapping->predicate, TRUE) ?? $mapping->predicate;
          if ($graph->hasProperty($resource, $property)) {
            // To iterate over all the objects from a resource and having the
            // same property, we need to use different methods, depending on
            // whether the object is a resource or a literal.
            $allMethod = $mapping->type === 'resource' ? 'allResources' : 'allLiterals';
            $valueMethod = $mapping->type === 'resource' ? 'getUri' : 'getValue';

            $delta = 0;
            foreach ($graph->{$allMethod}($resource, $property) as $object) {
              $value = $object->{$valueMethod}();
              if ($isTermReference && !empty($value)) {
                // Register the path to field item to be resolved later.
                $references[] = [$fieldName, $delta, $columnName];
              }
              $term[$fieldName][$delta][$columnName] = $value;
              if ($isFormattedText) {
                // Special treatment for formatted text fields.
                $term[$fieldName][$delta]['format'] = filter_default_format();
              }
              $delta++;
            }
          }
        }
      }

      // Some vocabularies are using reverse references, from parent to child.
      // @see skos:Collection
      $term['_children'] = array_map(function (Resource $child): string {
        return $child->getUri();
      }, $resource->allResources('skos:member'));

      $term['_references'] = $references;
      $terms[$resource->getUri()] = $term;
    }

    foreach ($terms as &$term) {
      // Convert reverse references to normal 'parent' references.
      foreach ($term['_children'] as $childUri) {
        if (isset($terms[$childUri])) {
          $terms[$childUri]['parent'] = $term['tid'];
        }
      }
      unset($term['_children']);

      // Replace all reference URIs to TIDs. Remove dead references.
      foreach ($term['_references'] as $trail) {
        $targetUri = NestedArray::getValue($term, $trail);
        if (isset($this->uriToTid[$targetUri])) {
          // Convert URI to TID.
          NestedArray::setValue($term, $trail, $this->uriToTid[$targetUri]);
        }
        else {
          // Dead references are removed.
          NestedArray::unsetValue($term, $trail);
        }
      }
      unset($term['_references']);
    }

    return $terms;
  }

  /**
   * Checks if a given RDF resource represents a taxonomy term.
   *
   * @param \EasyRdf\Resource $resource
   *   The RDF resource.
   * @param string $rdfType
   *   The predicate of the vocabulary.
   *
   * @return bool
   *   Whether the resource is a term.
   */
  private function isTerm(Resource $resource, string $rdfType): bool {
    if (!$vocabReference = $resource->getResource('skos:inScheme')) {
      if (!$vocabReference = $resource->getResource('skos:topConceptOf')) {
        return FALSE;
      }
    }
    return $vocabReference->getUri() === $rdfType;
  }

  /**
   * Picks-up the vocabularies corresponding to a given graph.
   *
   * @param string $graphUri
   *   The graph URI.
   *
   * @return string[]
   *   Associative array: vocabulary IDs as keys and their RDF type as values.
   */
  private function getVidsForGraph(string $graphUri): array {
    $vids = [];
    foreach ($this->vocabs as $vid => $data) {
      if ($data['graph_uri'] === $graphUri) {
        $vids[$vid] = $data['type'];
      }
    }
    return $vids;
  }

}
