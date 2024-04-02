<?php

declare(strict_types=1);

namespace Drupal\joinup_taxonomy;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\joinup_taxonomy\Entity\MappedTerm;
use Drupal\joinup_taxonomy\Event\PrepareVocabularyGraphEvent;
use Drupal\rdf_sync\RdfSyncMapper;
use Drupal\text\Plugin\Field\FieldType\TextItemBase;
use EasyRdf\Graph;
use EasyRdf\RdfNamespace;
use EasyRdf\Resource;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Used to update vocabularies.
 *
 * Usage:
 * @code
 * $graph1 = new Graph();
 * $graph1->parseFile('...');
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
   * Statistics collected during vocabularies update.
   */
  private array $stats = [];

  /**
   * List of term values keyed by URI.
   *
   * @var array[]
   *   Each item is an array of taxonomy term values suitable to be passed to
   *   TermStorage::create().
   */
  private array $termValues = [];

  /**
   * Collector for circular references.
   */
  private array $circularReferences = [];

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
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher service.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityFieldManagerInterface $fieldManager,
    protected FieldTypePluginManagerInterface $fieldTypeManager,
    protected RdfSyncMapper $mapper,
    protected Connection $database,
    protected EventDispatcherInterface $eventDispatcher,
  ) {}

  /**
   * Registers a graph to be processed into taxonomy terns.
   *
   * @param string $graphUri
   *   The graph URI.
   * @param \EasyRdf\Graph $graph
   *   The graph object.
   *
   * @return $this
   */
  public function registerGraph(string $graphUri, Graph $graph): self {
    $this->graphs[$graphUri] = $graph;
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

    foreach ($this->graphs as $graphUri => $graph) {
      if ($vids = $this->getVidsForGraph($graphUri)) {
        $this->updateFromGraph($vids, $graph);
      }
    }
    $stats = $this->stats;

    // Reset the registered internals.
    $this->vocabs = [];
    $this->graphs = [];
    $this->stats = [];
    $this->termValues = [];

    return $stats;
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
   */
  protected function updateFromGraph(array $vids, Graph $graph): void {
    /** @var \Drupal\taxonomy\VocabularyStorageInterface $vocabularyStorage */
    $vocabularyStorage = $this->entityTypeManager->getStorage('taxonomy_vocabulary');
    /** @var \Drupal\taxonomy\TermStorageInterface $termStorage */
    $termStorage = $this->entityTypeManager->getStorage('taxonomy_term');

    foreach ($vids as $vid => $rdfType) {
      $this->stats[$vid] = self::RESULT;

      /** @var \Drupal\taxonomy\VocabularyInterface $vocabulary */
      $vocabulary = $vocabularyStorage->load($vid);
      $isLockedVocabulary = $vocabulary->getThirdPartySetting('joinup_taxonomy', 'locked', FALSE);

      // Allow 3rd-party to alter the graph.
      $event = new PrepareVocabularyGraphEvent($vid, $graph);
      $this->eventDispatcher->dispatch($event);

      // Extract terms from graph and import them as Drupal entities.
      $this->importTerms($vid, $rdfType, $graph);

      if ($isLockedVocabulary) {
        // Locked vocabularies: remove existing terms missing from the graph.
        $processedUris = array_keys(
          array_filter(
            $this->termValues,
            function (array $values) use ($vid): bool {
              return $values['vid'] === $vid;
            },
          )
        );
        $processed = array_intersect_key($this->uriToTid, array_flip($processedUris));
        $existing = $this->mapper->getUrisByEntityType('taxonomy_term', $vid);
        $tidsToRemove = array_diff_key(array_flip($existing), $processed);
        if ($tidsToRemove) {
          $termStorage->delete($termStorage->loadMultiple($tidsToRemove));
          $this->stats[$vid]['deleted'] += count($tidsToRemove);
        }
      }
    }
  }

  /**
   * Returns a list of values needed for term entity creation.
   *
   * @param string $vid
   *   The RDF graph.
   * @param string $rdfType
   *   The RDF type.
   * @param \EasyRdf\Graph $graph
   *   The batch context passed by reference.
   */
  private function importTerms(string $vid, string $rdfType, Graph $graph): void {
    $fieldStorageDefinitions = $this->fieldManager->getFieldStorageDefinitions('taxonomy_term');
    $fieldDefinitions = $this->fieldManager->getFieldDefinitions('taxonomy_term', $vid);

    foreach ($graph->resources() as $resource) {
      if (!$this->isTerm($resource, $rdfType)) {
        continue;
      }

      $references = [];
      $values = ['vid' => $vid, 'uri' => $resource->getUri()];

      $mappings = $this->mapper->getMappings('taxonomy_term', $vid);
      // Already added.
      unset($mappings['vid']);
      foreach ($mappings as $fieldName => $columns) {
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
          $columnName = ($isFormattedText && $columnName === 'processed') ? 'value' : $columnName;

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
              if ($isTermReference) {
                // Register the path to field item to be resolved later.
                $references[$fieldName][] = $value;
                $values[$fieldName] = [];
              }
              else {
                $values[$fieldName][$delta][$columnName] = $value;
              }
              if ($isFormattedText) {
                // Special treatment for formatted text fields.
                $values[$fieldName][$delta]['format'] = filter_default_format();
              }
              $delta++;
            }
          }
        }
      }

      // Some vocabularies are using reverse references, from parent to child.
      // @see skos:Collection
      $values['_children'] = array_map(function (Resource $child): string {
        return $child->getUri();
      }, $resource->allResources('skos:member'));

      $values['_references'] = $references;
      $this->termValues[$values['uri']] = $values;
    }

    // Convert reverse references to normal 'parent' references.
    foreach ($this->termValues as $values) {
      if ($values['vid'] === $vid) {
        foreach ($values['_children'] as $childUri) {
          if (isset($this->termValues[$childUri])) {
            $this->termValues[$childUri]['_references']['parent'][] = $values['uri'];
          }
        }
      }
    }

    // Create or update the terms.
    foreach ($this->termValues as $uri => $values) {
      if (($values['vid'] === $vid) && !isset($this->uriToTid[$uri])) {
        $this->createOrUpdateTerm($uri);
      }
    }

    // We've collected unresolved circular references during term import. We
    // only re-save the missing references.
    $unresolvableReferences = [];
    foreach ($this->circularReferences as $uri => $reference) {
      if (!$term = MappedTerm::loadByUri($uri)) {
        throw new \Exception("Term with URI $uri doesn't exist");
      }
      foreach ($reference as [$fieldName, $dependencyUri]) {
        if ($dependencyTerm = MappedTerm::loadByUri($dependencyUri)) {
          $term->get($fieldName)->appendItem($dependencyTerm);
          continue;
        }
        $unresolvableReferences[] = "Dependent: $uri, field: $fieldName, dependency: $dependencyUri";
      }
      $term->save();
      unset($this->circularReferences[$uri]);
    }

    if ($unresolvableReferences) {
      throw new \Exception("Cannot resolve the following circular references, please fix the source of the import:\n" . implode("\n", $unresolvableReferences));
    }
  }

  /**
   * Creates or updates a taxonomy term.
   *
   * @param string $uri
   *   The term URI.
   * @param string|null $dependentFieldName
   *   Used internally. The name of the dependent field.
   * @param string|null $dependentUri
   *   Used internally. The URI of the dependent field.
   * @param array $processedUris
   *   Used internally. The URIs being processed but not saved yet.
   */
  private function createOrUpdateTerm(string $uri, ?string $dependentFieldName = NULL, ?string $dependentUri = NULL, array $processedUris = []): void {
    if (isset($processedUris[$uri])) {
      // Circular reference detected. This URI is in process of being resolved
      // but on other path. Register the unresolved edge and return to avoid
      // infinite recursion.
      $this->circularReferences[$dependentUri][] = [$dependentFieldName, $uri];
      return;
    }

    $values =& $this->termValues[$uri];

    // Normalize references.
    foreach ($values['_references'] as &$targetUris) {
      // Ensure unique references.
      $targetUris = array_values(array_unique($targetUris));
      // Remove references to itself. Some terms are referring themselves, see
      // EIRA v6.0.0 for http://data.europa.eu/dr8/InformationBusinessObject.
      $targetUris = array_filter($targetUris, function (string $targetUri) use ($uri): bool {
        return $targetUri !== $uri;
      });
    }
    $processedUris[$uri] = TRUE;
    // Unset because it's a reference var and we'll reuse the same name later.
    unset($targetUris);

    $termStorage = $this->entityTypeManager->getStorage('taxonomy_term');

    foreach ($values['_references'] as $fieldName => $targetUris) {
      foreach ($targetUris as $targetUri) {
        if (!isset($this->uriToTid[$targetUri]) && isset($this->termValues[$targetUri])) {
          $this->createOrUpdateTerm($targetUri, $fieldName, $uri, $processedUris);
        }
        if (isset($this->uriToTid[$targetUri])) {
          $values[$fieldName][] = $this->uriToTid[$targetUri];
        }
      }
    }
    unset($values['_references'], $values['_children']);

    if ($term = MappedTerm::loadByUri($uri)) {
      foreach ($values as $fieldName => $value) {
        $term->set($fieldName, $value);
      }
      $this->stats[$values['vid']]['updated']++;
    }
    else {
      $term = $termStorage->create($values);
      $this->stats[$values['vid']]['inserted']++;
    }

    // Save & register.
    $term->save();
    $this->uriToTid[$uri] = (int) $term->id();
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
