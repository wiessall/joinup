<?php

declare(strict_types = 1);

namespace Drupal\joinup_taxonomy\Commands;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\rdf_sync\RdfSyncMapper;
use Drupal\text\Plugin\Field\FieldType\TextItemBase;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use EasyRdf\Graph;
use EasyRdf\RdfNamespace;
use EasyRdf\Resource;

/**
 * Joinup taxonomy Drush commands.
 */
class JoinupTaxonomyCommands extends DrushCommands {

  /**
   * Fixtures location.
   *
   * @var string
   */
  private const FIXTURES_DIR = DRUPAL_ROOT . '/../.ddev/virtuoso/vocab';

  /**
   * Constructs a new Drush commands class instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\rdf_sync\RdfSyncMapper $mapper
   *   The RDF Sync mapper service.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected RdfSyncMapper $mapper,
  ) {
    parent::__construct();
  }

  /**
   * Creates a DB dump containing the terms of locked vocabularies.
   */
  #[CLI\Command(name: 'joinup:create-term-dump', aliases: ['jctd'])]
  #[CLI\Help(description: 'Create DB dump containing the terms of locked vocabularies')]
  public function createLockedVocabulariesTermDump(): void {
    $batchBuilder = (new BatchBuilder())->addOperation([self::class, 'init']);
    foreach (glob(self::FIXTURES_DIR . '/*.rdf') as $file) {
      $batchBuilder
        ->addOperation([self::class, 'startProcessFile'], [$file])
        ->addOperation([self::class, 'processFile'], [$file]);
    }
    $batchBuilder->setFinishCallback([self::class, 'finish']);
    batch_set($batchBuilder->toArray());
    drush_backend_batch_process();
  }

  /**
   * Initializes the batch process.
   *
   * @param array $context
   *   The batch context.
   */
  public static function init(array &$context): void {
    $mapper = \Drupal::getContainer()->get('rdf_sync.mapper');
    $vids = [];
    foreach (self::getLockedVocabularyIds() as $vid) {
      if ($type = $mapper->getBundleType('taxonomy_term', $vid)) {
        $vids[$type] = $vid;
      }
    }
    $context['results']['vids'] = $vids;
    $context['results']['pattern'] = '@' . implode('|', array_map('preg_quote', array_keys($vids))) . '@';
  }

  public static function startProcessFile(string $file, array &$context): void {
    $context['message'] = dt('Start processing !file', ['!file' => basename($file)]);
  }

  /**
   * Processes one fixture file.
   *
   * @param string $file
   *   The file path.
   * @param array $context
   *   The batch context.
   */
  public static function processFile(string $file, array &$context): void {
    $context['message'] = dt('❌ no destination vocabulary');

    $content = file_get_contents($file);

    // Bird eye check to avoid heavy graph parsing.
    if (!preg_match($context['results']['pattern'], $content)) {
      return;
    }

    $graph = new Graph();
    $graph->parse($content);

    if (!$vid = self::getVocabularyId($graph, $context['results']['vids'])) {
      return;
    }

    // Allow specific graph alters.
    $method = 'alterGraph' . ContainerBuilder::camelize($vid);
    if (method_exists(static::class, $method)) {
      static::$method($graph);
    }

    $terms = self::getTerms($graph, $vid);

    /** @var \Drupal\taxonomy\TermStorageInterface $termStorage */
    $termStorage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

    // Cleanup first.
    $tids = $termStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('vid', $vid)
      ->execute();
    if ($tids) {
      $termStorage->delete($termStorage->loadMultiple($tids));
    }

    foreach ($terms as $term) {
      $termStorage->create($term)->save();
    }

    $context['message'] = dt("✅ imported !count terms from !file into '!vocab' vocabulary", [
      '!count' => count($terms),
      '!file' => basename($file),
      '!vocab' => $vid,
    ]);
  }

  /**
   * Finishes the batch process.
   *
   * @param bool $success
   *   Whether the process ended successfully.
   * @param array $results
   *   The result sandbox.
   * @param array $operations
   *   List of performed operations.
   */
  public static function finish(bool $success, array $results, array $operations): void {
    $file = '../resources/fixtures/terms.sql';
    // Dump the taxonomy term tables.
    Drush::drush(Drush::aliasManager()->getSelf(), 'sql:dump', [], [
      'tables-list' => 'taxonomy_term_*',
      'result-file' => $file,
      'data-only' => TRUE,
      'gzip' => NULL,
      'no-interaction' => TRUE,
    ])->run();
    Drush::logger()->notice(dt('Terms dumped to !file', [
      '!file' => realpath("$file.gz"),
    ]));
  }

  /**
   * Retrieves the vocabulary ID given a graph.
   *
   * @param \EasyRdf\Graph $graph
   *   The RDF graph.
   * @param array $vids
   *   A list of locked vocabularies.
   *
   * @return string|null
   *   The vocabulary ID or NULL, if none.
   */
  private static function getVocabularyId(Graph $graph, array $vids): ?string {
    foreach ($graph->resources() as $resource) {
      if (in_array('skos:ConceptScheme', $resource->types(), TRUE)) {
        return $vids[$resource->getUri()] ?? NULL;
      }
    }
    return NULL;
  }

  /**
   * Processes the graph and return a list of terms.
   *
   * @param \EasyRdf\Graph $graph
   *   The RDF graph.
   * @param string $vid
   *   The vocabulary ID.
   *
   * @return array
   *   A list of terms as arrays. Each array is suitable to be passed to the
   *   Term::create() method.
   */
  private static function getTerms(Graph $graph, string $vid): array {
    $fieldManager = \Drupal::getContainer()->get('entity_field.manager');

    $fieldStorageDefinitions = $fieldManager->getFieldStorageDefinitions('taxonomy_term');
    $fieldDefinitions = $fieldManager->getFieldDefinitions('taxonomy_term', $vid);
    $fieldTypeManager = \Drupal::getContainer()->get('plugin.manager.field.field_type');
    $mapper = \Drupal::getContainer()->get('rdf_sync.mapper');

    $terms = [];
    // For performance reasons, we assign TIDs to terms before they are saved,
    // so that we can compute the entity reference fields between terms (parent,
    // related, etc.) without needing successive entity saves. Getting and using
    // the next auto-increment is not safe in 99.99% of the cases. However, here
    // we strongly assume that no parallel process is underway as this Drush
    // command is used in very rarely and only by developers, only when they
    // re-create the taxonomy terms MySQL dump.
    $tid = \Drupal::database()->query("SELECT MAX(tid) FROM {taxonomy_term_data}")->fetchField() + 1;
    foreach ($graph->resources() as $resource) {
      if (!in_array($resource->type(), ['skos:Concept', 'skos:Collection'], TRUE)) {
        continue;
      }

      $references = [];
      $term = [
        'tid' => $tid++,
        'vid' => $vid,
        'uri' => $resource->getUri(),
      ];
      $mappings = $mapper->getMappings('taxonomy_term', $vid);
      // Already added.
      unset($mappings['vid']);
      foreach ($mappings as $fieldName => $columns) {
        /** @var \Drupal\rdf_sync\ColumnMapping $mapping */
        foreach ($columns as $columnName => $mapping) {
          $fieldType = $fieldStorageDefinitions[$fieldName]->getType();
          $fieldTypePlugin = $fieldTypeManager->getDefinition($fieldType);

          $isTermReference = FALSE;
          if (is_a($fieldTypePlugin['class'], EntityReferenceItem::class, TRUE)) {
            // Check if this field is an entity reference to another term.
            $fieldDefinition = $fieldDefinitions[$fieldName];
            if ($fieldDefinition->getSetting('target_type') === 'taxonomy_term') {
              $handlerSettings = $fieldDefinition->getSetting('handler_settings');
              $isTermReference = $handlerSettings['target_bundles'] === NULL || isset($handlerSettings['target_bundles'][$vid]);
            }
          }

          $isFormattedText = is_a($fieldTypePlugin['class'], TextItemBase::class, TRUE);
          // Change 'processed' to 'value' on data import.
          $columnName = $isFormattedText && $columnName === 'processed' ? 'value' : $columnName;

          $property = RdfNamespace::shorten($mapping->predicate) ?? $mapping->predicate;
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
        if (isset($terms[$targetUri])) {
          // Convert URI to TID.
          NestedArray::setValue($term, $trail, $terms[$targetUri]['tid']);
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
   * Returns a list of locked vocabulary IDs.
   *
   * @return string[]
   *   A list of vocabulary IDs.
   */
  private static function getLockedVocabularyIds(): array {
    /** @var \Drupal\taxonomy\VocabularyStorageInterface $vocabularyStorage */
    $vocabularyStorage = \Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary');
    return array_values($vocabularyStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('third_party_settings.joinup_taxonomy.locked', TRUE)
      ->execute());
  }

  /**
   * Alters the 'language' vocabulary graph.
   *
   * @param \EasyRdf\Graph $graph
   *   The graph to be altered.
   */
  private static function alterGraphLanguage(Graph &$graph): void {
    $newGraph = new Graph();
    foreach ($graph->resources() as $resource) {
      if ($resource->type() === 'skos:Concept') {
        foreach ($graph->allLiterals($resource, 'skos:prefLabel', 'en') as $object) {
          if ($object->getValue() !== 'Multilingual Code') {
            $newResource= new Resource($resource->getUri(), $newGraph);
            $newResource->setType('skos:Concept');
            $newGraph->addResource($newResource, new Resource('http://www.w3.org/2004/02/skos/core#inScheme'), new Resource('http://publications.europa.eu/resource/authority/language'));
            $newGraph->addLiteral($newResource, 'skos:prefLabel', $object->getValue());
          }
        }
      }
    }
    $graph = $newGraph;
  }

}
