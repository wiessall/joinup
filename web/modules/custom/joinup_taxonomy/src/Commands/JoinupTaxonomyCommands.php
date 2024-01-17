<?php

declare(strict_types = 1);

namespace Drupal\joinup_taxonomy\Commands;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Crypt;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
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

  // BEGIN: testing code.
  const CHECK = [
    'actor' => 20,
    'business_process' => 249,
    'eif_conceptual_model' => 8,
    'eif_interoperability_layer' => 6,
    'eif_principle' => 12,
    'eif_recommendation' => 47,
    'eif_solution_type' => 7,
    'file_type' => 120,
    'language' => 262,
    'themes' => 14,
    'eurovoc' => 0,
    'licence_type' => 15,
    'owner_type' => 11,
    'product_type' => 9454,
    'spatial' => 253,
    'standardization_level' => 3,
    'status' => 4,
    'test_resource_type' => 7,
    'representation_technique' => 27,
    'solution_category' => 2,
    // @todo Ilias reported 797. Double check this.
    'eira' => 535,
  ];
  // END: testing code.

  /**
   * List of locked vocabularies grouped by their fixture file.
   *
   * The order is important for vocabularies whose terms are referring to other
   * vocabularies (e.g., eif_* vocabularies).
   *
   * @const string[][]
   */
  private const VOCABS = [
    'actor.rdf' => ['actor'],
    'ADMS-SW-v1.00.rdf' => ['owner_type', 'licence_type'],
    'ADMS_SKOS_v1.00.rdf' => ['representation_technique'],
    'business-process.rdf' => ['business_process'],
    'countries-skos.rdf' => ['spatial'],
    'data-theme-skos.rdf' => ['themes'],
    'eif_conceptual_model.rdf' => ['eif_conceptual_model'],
    'eif_interoperability_layer.rdf' => ['eif_interoperability_layer'],
    'eif_principle.rdf' => ['eif_principle'],
    // References eif_conceptual_model, eif_interoperability_layer,
    // eif_principle. That's why comes last.
    'eif_recommendation.rdf' => ['eif_recommendation'],
    'eif_solution_type.rdf' => ['eif_solution_type'],
    'EIRA_SKOS.rdf' => ['eira'],
    'filetypes-skos.rdf' => ['file_type'],
    'languages-skos.rdf' => ['language'],
    'product_type.rdf' => ['product_type'],
    'solution_category.rdf' => ['solution_category'],
    'standardization_level.rdf' => ['standardization_level'],
    'test_resource_type.rdf' => ['test_resource_type'],
  ];

  /**
   * Fixtures location.
   *
   * @var string
   */
  private const FIXTURES_DIR = DRUPAL_ROOT . '/../.ddev/virtuoso/vocab';

  /**
   * Static cache of dump file path.
   */
  private static string $dumpFile;

  /**
   * Creates locked vocabularies terms.
   */
  #[CLI\Command(name: 'joinup:create-locked-vocabulary-terms')]
  #[CLI\Help(
    description: 'Create terms in locked vocabularies',
    synopsis: 'If there are changes to the RDF files or the MySQL dump file is missing, the command will recreate the MySQL dump file',
  )]
  public function createLockedVocabulariesTermDump(): void {
    if (file_exists($this->getDumpFilePath())) {
      // We already have a valid MySQL dump file. Restore terms from dump.
      $this->createTermsFromDump();
      Drush::logger()->notice(dt('✅ created locked vocabularies terms from MySQL dump'));
      return;
    }

    // Either the dump file is missing or source RDF files have changes. We
    // recreate the MySQL dump file in a batch process.
    $batchBuilder = (new BatchBuilder())->addOperation([self::class, 'init']);
    foreach (array_keys(self::VOCABS) as $file) {
      $batchBuilder->addOperation([self::class, 'processFile'], [$file]);
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
    foreach (\Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary')->loadMultiple() as $vid => $vocabulary) {
      if ($type = $mapper->getBundleType('taxonomy_term', $vid)) {
        $vids[$type] = $vid;
      }
    }

    $context['results']['vids'] = $vids;
    $context['results']['pattern'] = '@' . implode('|', array_map('preg_quote', array_keys($vids))) . '@';
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
    $content = file_get_contents(self::FIXTURES_DIR . '/' . $file);

    // Bird eye check to avoid heavy graph parsing.
    if (!preg_match($context['results']['pattern'], $content)) {
      $context['message'] = dt('❌ no destination vocabulary');
      return;
    }

    $graph = new Graph();
    $graph->parse($content);

    $message = [];
    $entityTypeManager = \Drupal::entityTypeManager();
    /** @var \Drupal\taxonomy\VocabularyStorageInterface $vocabularyStorage */
    $vocabularyStorage = $entityTypeManager->getStorage('taxonomy_vocabulary');
    /** @var \Drupal\taxonomy\TermStorageInterface $termStorage */
    $termStorage = $entityTypeManager->getStorage('taxonomy_term');
    foreach (self::VOCABS[$file] as $vid) {
      /** @var \Drupal\taxonomy\VocabularyInterface $vocabulary */
      $vocabulary = $vocabularyStorage->load($vid);
      $isLockedVocabulary = $vocabulary->getThirdPartySetting('joinup_taxonomy', 'locked', FALSE);

      self::prepareGraph($graph, $vid);

      // Allow specific vocabulary alters.
      $method = 'alterGraphFor' . ContainerBuilder::camelize($vid);
      if (method_exists(static::class, $method)) {
        static::$method($graph);
      }

      // Extract terms from graph.
      $terms = self::getTerms($graph, $vid, $context);

      foreach ($terms as $term) {
        if ($existingTerms = $termStorage->loadByProperties(['uri' => $term['uri']])) {
          // Delete if already exists.
          reset($existingTerms)->delete();
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
        }
      }

      $message[] = dt('!count terms into !vocab', [
        '!count' => count($terms),
        '!vocab' => $vid,
      ]);

      // BEGIN: testing code.
      $c = $termStorage->getQuery()
        ->accessCheck(FALSE)
        ->count()
        ->condition('vid', $vid)
        ->execute();
      if ($c != self::CHECK[$vid]) {
        throw new \Exception("$vid expects " . static::CHECK[$vid] . " got $c");
      }
      // END: testing code.
    }

    $context['message'] = dt("✅ imported !counts from !file", [
      '!counts' => implode(', ', $message),
      '!file' => $file,
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
    $file = static::getDumpFilePath(FALSE);

    // Dump the taxonomy term tables.
    Drush::drush(Drush::aliasManager()->getSelf(), 'sql:dump', [], [
      'tables-list' => 'taxonomy_term_*',
      'result-file' => $file,
      'gzip' => NULL,
      'no-interaction' => TRUE,
    ])->run();

    $file = realpath("$file.gz");

    // Remove stale dump files.
    $staleFiles = array_filter(
      glob(self::FIXTURES_DIR . '/*.sql.gz'),
      function (string $staleFile) use ($file): bool {
        return realpath($staleFile) !== $file;
      }
    );
    array_walk($staleFiles, function (string $file): void {
      \Drupal::getContainer()->get('file_system')->unlink($file);
    });

    Drush::logger()->notice(dt('Terms dumped to !file', ['!file' => $file]));
  }

  /**
   * Processes the graph and return a list of terms.
   *
   * @param \EasyRdf\Graph $graph
   *   The RDF graph.
   * @param string $vid
   *   The vocabulary ID.
   * @param array $context
   *   The batch context passed by reference.
   *
   * @return array
   *   A list of terms as arrays. Each array is suitable to be passed to the
   *   Term::create() method.
   */
  private static function getTerms(Graph $graph, string $vid, array &$context): array {
    $rdfType = array_search($vid, $context['results']['vids'], TRUE);

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
      if (!static::isTerm($resource, $rdfType)) {
        continue;
      }

      $references = [];
      $term = [
        'tid' => $tid++,
        'vid' => $vid,
        'uri' => $resource->getUri(),
      ];

      // Register the URI > TID mapping (used to resolve references).
      $context['results']['uri_to_tid'][$resource->getUri()] = $term['tid'];

      $mappings = $mapper->getMappings('taxonomy_term', $vid);
      // Already added.
      unset($mappings['vid']);
      foreach ($mappings as $fieldName => $columns) {
        /** @var \Drupal\rdf_sync\Model\ColumnMapping $mapping */
        foreach ($columns as $columnName => $mapping) {
          $fieldType = $fieldStorageDefinitions[$fieldName]->getType();
          $fieldTypePlugin = $fieldTypeManager->getDefinition($fieldType);

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
        if (isset($context['results']['uri_to_tid'][$targetUri])) {
          // Convert URI to TID.
          NestedArray::setValue($term, $trail, $context['results']['uri_to_tid'][$targetUri]);
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
   * Prepares the graph.
   *
   * @param \EasyRdf\Graph $graph
   *   The graph to be cleaned up.
   * @param string $vid
   *   The vocabulary.
   */
  private static function prepareGraph(Graph $graph, string $vid): void {
    foreach ($graph->resources() as $resource) {
      foreach ($resource->propertyUris() as $propertyUri) {
        // Remove any non-English version of the taxonomy terms since we are
        // only supporting an English version on the website. If more terms are
        // needed, the supported languages should be extended (the "en" below).
        // This needs to repeat multiple times as the terms might.
        $predicate = RdfNamespace::shorten($propertyUri) ?? $propertyUri;
        foreach ($resource->allLiterals($predicate) as $literal) {
          $lang = $literal->getLang();
          if ($lang && $lang !== 'en') {
            $graph->deleteLiteral($resource, new Resource($propertyUri), $literal, $lang);
          }
        }
      }
    }
  }

  /**
   * Alters the 'language' vocabulary graph.
   *
   * @param \EasyRdf\Graph $graph
   *   The graph to be altered.
   */
  private static function alterGraphForLanguage(Graph $graph): void {
    foreach ($graph->resources() as $resource) {
      // If it's not a "skos:Concept", then it's not a language vocabulary term.
      // Remove this resource and continue with the next one.
      if ($resource->type() !== 'skos:Concept') {
        $graph->deleteEntireResource($resource);
        continue;
      }

      // This resource has no labels. Remove it and continue with the next one.
      if (!$objects = $graph->allLiterals($resource, 'skos:prefLabel')) {
        $graph->deleteEntireResource($resource);
        continue;
      }

      // We get our languages from the Metadata Registry. The Metadata Registry
      // maintains two authority tables, one for individual languages and one
      // for groups of languages called multilingual. For legacy reasons, the
      // two tables are published as a merger of the two. The multilingual
      // language groups are not useful for us, and we need to filter them out
      // to avoid having the language lists polluted with entries labeled
      // 'Multilingual Code'.
      // @see http://publications.europa.eu/mdr/resource//documentation/schema/cat.html#element_languages
      // @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-2764
      foreach ($objects as $object) {
        if ($object->getValue() === 'Multilingual Code') {
          $graph->deleteEntireResource($resource);
          // Pickup next resource.
          break;
        }
      }
    }
  }

  /**
   * Alters the 'spatial' vocabulary graph.
   *
   * @param \EasyRdf\Graph $graph
   *   The graph to be altered.
   */
  private static function alterGraphForSpatial(Graph $graph): void {
    // Remove deprecated countries from the country list. See ISAICP-3442.
    foreach ($graph->resources() as $resource) {
      if ($graph->hasProperty($resource, 'http://publications.europa.eu/ontology/authority/end.use')) {
        $graph->deleteEntireResource($resource);
      }
    }
  }

  /**
   * Alters the 'file_type' vocabulary graph.
   *
   * Extract extensions and mime-type from referred BNodes and fill two literals
   * that can be mapped. Due to the extensions and mime-type RDF representation,
   * this cannot be done by a simple RDF field mapping.
   *
   * @param \EasyRdf\Graph $graph
   *   The graph to be altered.
   */
  private static function alterGraphForFileType(Graph $graph): void {
    foreach ($graph->resources() as $resource) {
      if ($resource->type() === 'skos:Concept') {
        $mappedCode = $resource->allResources(RdfNamespace::shorten('http://publications.europa.eu/ontology/authority/op-mapped-code', TRUE));
        $extensions = [];
        $mimeType = NULL;
        /** @var \EasyRdf\Resource $reference */
        foreach ($mappedCode as $reference) {
          if ($reference->hasProperty('http://purl.org/dc/elements/1.1/source', 'file-extension')) {
            // A list of extensions separated by space might be provided.
            $extension = preg_split('/\s/', trim($reference->getLiteral(RdfNamespace::shorten('http://publications.europa.eu/ontology/authority/legacy-code', TRUE))->getValue()));
            $extensions = array_unique([...$extensions, ...$extension]);
          }
          if ($reference->hasProperty('http://purl.org/dc/elements/1.1/source', 'manifestation-type-cellar')) {
            $mimeType ??= trim($reference->getLiteral(RdfNamespace::shorten('http://publications.europa.eu/ontology/authority/legacy-code', TRUE))->getValue());
          }
        }
        if ($mimeType) {
          $graph->addLiteral($resource, 'http://joinup.eu/file-type/mime-type', $mimeType);
        }
        if ($extensions) {
          $graph->addLiteral($resource, RdfNamespace::shorten('http://joinup.eu/file-type/extension', TRUE), $extensions);
        }
      }
    }
  }

  /**
   * Alters the 'owner_type' vocabulary graph.
   *
   * @param \EasyRdf\Graph $graph
   *   The graph to be altered.
   */
  private static function alterGraphForOwnerType(Graph $graph): void {
    // @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-3084
    $graph->addResource(
      'http://purl.org/adms/licencetype/ViralEffect-ShareAlike',
      'http://www.w3.org/2004/02/skos/core#inScheme',
      'http://purl.org/adms/licencetype/1.1',
    );
  }

  /**
   * Alters the 'eira' vocabulary graph.
   *
   * @param \EasyRdf\Graph $graph
   *   The graph to be altered.
   */
  private static function alterGraphForEira(Graph $graph): void {
    RdfNamespace::set('eira', 'http://data.europa.eu/dr8/');
    // Replace the `dc` namespace with `dct` - requested by the client.
    RdfNamespace::delete('dc');
    RdfNamespace::delete('dcterms');
    RdfNamespace::set('dct', 'http://purl.org/dc/terms/');
    RdfNamespace::set('owlturtle', 'http://www.semanticweb.org/owl/owlapi/turtle#');

    foreach ($graph->resources() as $resource) {
      if (str_ends_with($resource->getUri(), '_Properties')) {
        // For EIRA_SKOS, after version 6, they have added all extra properties
        // of the EIRA terms as separate terms in the graph. To properly handle
        // these secondary terms in the website, we extract the extra-properties
        // and inject them, as a Json serialized array, into the main term.
        $namespaces = $rows = [];
        $mainResource = $resource->getResource('skos:related');
        foreach ($resource->propertyUris() as $propertyUri) {
          $shortenedProperty = RdfNamespace::shorten($propertyUri);
          // Do not store the skos:topConceptOf and skos:related properties.
          if (in_array($shortenedProperty, ['skos:topConceptOf', 'skos:related'], TRUE)) {
            continue;
          }
          $prefix = RdfNamespace::prefixOfUri($propertyUri);
          $namespaces[$prefix] = [
            'namespace' => $prefix . ':',
            'uri' => RdfNamespace::get($prefix),
          ];
          foreach ($graph->all($resource, $shortenedProperty) as $object) {
            $value = $object instanceof Resource ? $object->getUri() : $object->getValue();
            if ($value) {
              $rows[] = [
                'property' => $shortenedProperty,
                'value' => $value,
              ];
            }
          }
        }

        // Inject properties in the main term.
        $graph->addLiteral($mainResource, 'http://joinup.eu/eira/properties', Json::encode([
          'namespaces' => $namespaces,
          'rows' => $rows,
        ]));

        // We've extracted what we need. Drop this resource and jump to next.
        $graph->deleteEntireResource($resource);
        continue;
      }

      if ($resource->isA('skos:Collection')) {
        // Add the skos:Concept type to all collection elements so that they are
        // listed as parent terms.
        // @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-3216
        $graph->addType($resource, 'skos:Concept');

        foreach ($graph->allResources($resource, 'skos:member') as $member) {
          // Create a backwards connection from the children to the parent.
          $graph->addResource($member, 'skos:broaderTransitive', $resource);
        }
      }

      // Add the link to all skos:Concept type elements so that they are all
      // considered as children of the vocabulary regardless of their depth.
      if ($resource->isA('skos:Concept')) {
        $graph->addResource($resource, 'skos:topConceptOf', 'http://data.europa.eu/dr8');
      }
    }
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
  private static function isTerm(Resource $resource, string $rdfType): bool {
    if (!$vocabReference = $resource->getResource('skos:inScheme')) {
      if (!$vocabReference = $resource->getResource('skos:topConceptOf')) {
        return FALSE;
      }
    }
    return $vocabReference->getUri() === $rdfType;
  }

  /**
   * Returns the path to the dump file.
   *
   * @param bool $compressed
   *   Whether to append .gz at the end.
   *
   * @return string
   *   A file path.
   */
  private static function getDumpFilePath(bool $compressed = TRUE): string {
    if (!isset(static::$dumpFile)) {
      $checksum = '';
      foreach (array_keys(self::VOCABS) as $file) {
        $checksum .= sha1_file(self::FIXTURES_DIR . '/' . $file);
      }
      $checksum = Crypt::hashBase64($checksum);
      static::$dumpFile = self::FIXTURES_DIR . "/terms.$checksum.sql";
    }
    return static::$dumpFile . ($compressed ? '.gz' : '');
  }

  /**
   * Creates the locked vocabularies terms from MySQL dump.
   */
  private function createTermsFromDump(): void {
    $mysql = sprintf(
      'mysql -u%s%s -h%s %s',
      getenv('DRUPAL_DATABASE_USERNAME'),
      getenv('DRUPAL_DATABASE_PASSWORD') ? ' -p' . getenv('DRUPAL_DATABASE_PASSWORD') : '',
      getenv('DRUPAL_DATABASE_HOST'),
      getenv('DRUPAL_DATABASE_NAME'),
    );
    exec('gunzip < ' . static::getDumpFilePath() . ' | ' . $mysql);
  }

}
