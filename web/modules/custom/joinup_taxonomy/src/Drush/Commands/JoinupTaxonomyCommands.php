<?php

declare(strict_types=1);

namespace Drupal\joinup_taxonomy\Drush\Commands;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\joinup_taxonomy\VocabularyUpdater;
use Drupal\rdf_sync\RdfSyncConnection;
use Drupal\rdf_sync\RdfSyncMapper;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use EasyRdf\Graph;
use EasyRdf\Literal;
use EasyRdf\RdfNamespace;
use EasyRdf\Resource;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Joinup taxonomy Drush commands.
 */
class JoinupTaxonomyCommands extends DrushCommands {

  /**
   * List of locked vocabularies grouped by their fixture file.
   *
   * The order is important for vocabularies whose terms are referring to other
   * vocabularies (e.g., eif_* vocabularies).
   *
   * @const string[][]
   */
  private const VOCABS = [
    'ADMS-SW-v1.00.rdf' => ['owner_type', 'licence_type'],
    'ADMS_SKOS_v1.00.rdf' => ['representation_technique', 'status'],
    'countries-skos.rdf' => ['spatial'],
    'eif_conceptual_model.rdf' => ['eif_conceptual_model'],
    'eif_interoperability_layer.rdf' => ['eif_interoperability_layer'],
    'eif_principle.rdf' => ['eif_principle'],
    // References eif_conceptual_model, eif_interoperability_layer,
    // eif_principle. That's why it comes last.
    'eif_recommendation.rdf' => ['eif_recommendation'],
    'eif_solution_type.rdf' => ['eif_solution_type'],
    'EIRA_SKOS.rdf' => ['eira'],
    'filetypes-skos.rdf' => ['file_type'],
    'languages-skos.rdf' => ['language'],
    'solution_category.rdf' => ['solution_category'],
  ];

  /**
   * Fixtures location.
   *
   * @var string
   */
  private const FIXTURES_DIR = DRUPAL_ROOT . '/../resources/vocab';

  /**
   * Static cache for vocabulary updater service.
   */
  private static VocabularyUpdater $vocabularyUpdater;

  /**
   * Constructs a new Drush commands class instance.
   *
   * @param \Drupal\rdf_sync\RdfSyncMapper $mapper
   *   The RDF Sync mapper service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   */
  public function __construct(
    private readonly RdfSyncMapper $mapper,
    private readonly ConfigFactoryInterface $configFactory,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('rdf_sync.mapper'),
      $container->get('config.factory'),
    );
  }

  /**
   * Creates locked vocabularies terms.
   */
  #[CLI\Command(name: 'joinup:create-locked-vocabulary-terms')]
  #[CLI\Option(name: 'generate', description: 'Recreate the MySQL dump file')]
  #[CLI\Help(
    description: 'Create terms in locked vocabularies',
    synopsis: 'Imports the locked vocabularies terms from a MySQL dump file. To re-generate a new dump file, pass the --generate option',
  )]
  public function createLockedVocabulariesTermDump(array $options = ['generate' => FALSE]): void {
    if (!$options['generate']) {
      // Restore terms from dump.
      $this->createTermsFromDump();
      Drush::logger()->notice(dt('✅ created locked vocabularies terms from MySQL dump'));
      return;
    }

    // Recreate the MySQL dump file in a batch process.
    $batchBuilder = (new BatchBuilder())->addOperation([self::class, 'init']);
    foreach (array_keys(self::VOCABS) as $file) {
      $batchBuilder->addOperation([self::class, 'processFile'], [$file]);
    }
    $batchBuilder->setFinishCallback([self::class, 'finish']);

    batch_set($batchBuilder->toArray());
    drush_backend_batch_process();
  }

  /**
   * Synchronizes Drupal terms to RDF backend.
   */
  #[CLI\Command(name: 'joinup:sync-locked-vocabulary-terms')]
  #[CLI\Option(name: 'generate', description: 'Recreate the RDF-XML dump file')]
  #[CLI\Help(
    description: 'Synchronize Drupal terms to RDF backend',
    synopsis: 'Synchronizes the locked vocabulary terms from a RDF-XML dump file to the RDF backend. To re-generate a new RDF-XML dump file, pass the --generate option',
  )]
  public function synchronizeTerms(array $options = ['generate' => FALSE]): void {
    if (!$options['generate']) {
      // Restore terms from dump.
      $this->synchronizeTermsFromRdfXmlFile();
      Drush::logger()->notice(dt('✅ synchronized locked vocabularies terms from RDFXML dump'));
      return;
    }

    $vids = array_merge(...array_values(self::VOCABS));
    // Collect triples in this graph to create a fixture file later.
    $fixtureFileGraph = new Graph();
    $vocabularyUris = [];

    // Add the vocabulary entries.
    foreach (\Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary')->loadMultiple($vids) as $vid => $vocabulary) {
      $vocabularyUri = $this->mapper->getBundleRdfType('taxonomy_term', $vid);
      $triples = [
        $vocabularyUri => [
          'http://www.w3.org/1999/02/22-rdf-syntax-ns#type' => [
            (new Resource('http://www.w3.org/2004/02/skos/core#ConceptScheme'))->toRdfPhp(),
          ],
          'http://www.w3.org/2004/02/skos/core#prefLabel' => [
            (new Literal($vocabulary->label(), 'en'))->toRdfPhp(),
          ],
        ],
      ];
      RdfSyncConnection::updateTriples($triples);

      // Add 'eira' and 'adms' namespaces.
      RdfNamespace::set('eira', 'http://data.europa.eu/dr8/');
      RdfNamespace::set('adms', 'http://www.w3.org/ns/adms#');

      $fixtureFileGraph->parse($triples, 'php');
      $vocabularyUris[] = $vocabularyUri;
    }

    // We already have a Drush command. Use it.
    $process = Drush::drush(
      Drush::aliasManager()->getSelf(),
      'rdf_sync:synchronize',
      ['taxonomy_term'],
      ['bundle' => implode(',', $vids)],
    );
    $process
      ->disableOutput()
      ->run($process->showRealtime());

    $query = <<<QUERY
      SELECT ?subject ?predicate ?object
      WHERE {
        GRAPH <%s> {
          ?subject ?predicate ?object .
          ?subject a <http://www.w3.org/2004/02/skos/core#Concept> .
          ?subject ?vocab_type ?vocab .
          VALUES ?vocab_type { <http://www.w3.org/2004/02/skos/core#inScheme> <http://www.w3.org/2004/02/skos/core#topConceptOf> } .
          VALUES ?vocab { %s } .
        }
      }
      QUERY;

    // Get all locked vocabulary triples and store them in the collector graph.
    $graphUri = $this->configFactory->get('rdf_sync.settings')->get('graph_uri');
    $results = RdfSyncConnection::query(sprintf($query, $graphUri, '<' . implode('> <', $vocabularyUris) . '>'));
    foreach ($results as $row) {
      $fixtureFileGraph->add($row->subject, $row->predicate, $row->object);
    }

    // Generate the RDFXML dump file.
    $file = static::getDumpFilePath('rdf', FALSE);
    file_put_contents($file, $fixtureFileGraph->serialise('rdfxml'));

    $this->logger()->notice(dt('Triples dumped to !file', [
      '!file' => realpath($file),
    ]));
  }

  /**
   * Initializes the batch process.
   *
   * @param array $context
   *   The batch context.
   */
  public static function init(array &$context): void {
    $mapper = \Drupal::getContainer()->get('rdf_sync.mapper');
    $vids = array_merge(...array_values(self::VOCABS));
    foreach (\Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary')->loadMultiple($vids) as $vid => $vocabulary) {
      if ($type = $mapper->getBundleRdfType('taxonomy_term', $vid)) {
        $context['results']['vids'][$type] = $vid;
      }
    }
    $context['results']['uri_to_tid'] = [];
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
    $graph = new Graph();
    $graph->parseFile(self::FIXTURES_DIR . '/' . $file);
    $graphUri = 'http://' . strtolower(pathinfo($file, PATHINFO_FILENAME));

    foreach (self::VOCABS[$file] as $vid) {
      $rdfType = array_flip($context['results']['vids'])[$vid];
      self::getVocabularyUpdater()->registerVocabulary($vid, $rdfType, $graphUri);
    }

    $results = self::getVocabularyUpdater()
      // Pass the already collected, URI <-> TID mappings to resolve
      // dependencies between terms from different vocabularies.
      ->setUriToTid($context['results']['uri_to_tid'])
      ->registerGraph($graphUri, $graph)
      ->update();

    // Get the updated list and make it available to the next batch operation.
    $context['results']['uri_to_tid'] = self::getVocabularyUpdater()->getUriToTid();

    foreach ($results as $vid => $result) {
      $stats = [];
      foreach ($result as $op => $count) {
        $stats[] = "$count $op";
      }
      Drush::logger()->notice(dt('✅ From !file into !vid (!stats)', [
        '!file' => $file,
        '!vid' => $vid,
        '!stats' => implode(', ', $stats),
      ]));
    }
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
    $file = static::getDumpFilePath('sql', FALSE);
    $vids = array_values($results['vids']);

    // Add MySQL cleanup statements.
    file_put_contents($file, implode("\n", static::getCleanupStatements($vids)) . "\n\n");

    // Dump the taxonomy term tables.
    $command = Drush::drush(Drush::aliasManager()->getSelf(), 'sql:dump', [], [
      'tables-list' => 'taxonomy_term_*',
      'data-only' => TRUE,
      'no-interaction' => TRUE,
    ]);
    // Handle as buffered output to avoid big strings in memory.
    $command->run(function ($type, $buffer) use ($file): void {
      if ($type === Process::OUT) {
        file_put_contents($file, $buffer, FILE_APPEND);
      }
    });
    if (!$command->isSuccessful()) {
      throw new ProcessFailedException($command);
    }
    $command->clearOutput();

    // Append the {rdf_sync_uri} table fixtures inside the dump file.
    file_put_contents($file, static::getUriTableDump($results['uri_to_tid'], $vids), FILE_APPEND);

    // Gzip it and get the normalized path.
    exec("gzip -f $file");
    $file = realpath("$file.gz");
    Drush::logger()->notice(dt('Terms dumped to !file', ['!file' => $file]));
  }

  /**
   * Returns a list of MySQL statements to clean up existing records.
   *
   * @param array $vids
   *   The list of locked vocabularies.
   *
   * @return string[]
   *   A list of MySQL DELETE statements.
   */
  protected static function getCleanupStatements(array $vids): array {
    /** @var \Drupal\taxonomy\TermStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $sharedData = ['taxonomy_term_data', 'taxonomy_term_field_data'];
    $sharedRevision = ['taxonomy_term_revision', 'taxonomy_term_field_revision'];
    $prefix = \Drupal::database()->getPrefix();

    $in = "'" . implode("', '", $vids) . "'";
    foreach ($sharedRevision as $table) {
      // The shared revision table lacks a bundle column.
      $statements[] = "DELETE FROM `$prefix$table` WHERE tid IN(SELECT tid FROM `{$prefix}taxonomy_term_data` WHERE vid IN ($in));";
    }

    $tables = array_diff($storage->getTableMapping()->getTableNames(), $sharedRevision);
    foreach ($tables as $table) {
      $bundleColumn = in_array($table, $sharedData, TRUE) ? 'vid' : 'bundle';
      $statements[] = "DELETE FROM `$prefix$table` WHERE $bundleColumn IN ($in);";
    }
    $statements[] = "DELETE FROM `{$prefix}rdf_sync_uri` WHERE entity_type = 'taxonomy_term' AND bundle IN ($in);";

    return $statements;
  }

  /**
   * Returns the dump corresponding to the {rdf_sync_uri} table.
   *
   * @param array $uriToTid
   *   The URI <-> TID map.
   * @param string[] $vids
   *   The locked vocabulary IDs.
   *
   * @return string
   *   The SQL dump.
   */
  protected static function getUriTableDump(array $uriToTid, array $vids): string {
    $termStorage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $table = \Drupal::database()->getPrefix() . 'rdf_sync_uri';
    $tidToUri = array_flip($uriToTid);

    $statements = [
      "LOCK TABLES `$table` WRITE;",
      'set autocommit=0;',
    ];

    foreach (array_chunk($uriToTid, 100) as $chunk) {
      $statement = "INSERT INTO `$table` VALUES ";
      $values = [];
      foreach ($termStorage->loadMultiple($chunk) as $tid => $term) {
        $values[] = "('taxonomy_term',$tid,'$tidToUri[$tid]','{$term->bundle()}')";
      }
      $statement .= implode(',', $values) . ';';
      $statements[] = $statement;
    }

    $statements[] = 'UNLOCK TABLES;';
    $statements[] = 'commit;';

    return implode("\n", $statements);
  }

  /**
   * Returns the path to the dump file.
   *
   * @param string $extension
   *   The file extension.
   * @param bool $compressed
   *   Whether to append .gz at the end.
   *
   * @return string
   *   A file path.
   */
  public static function getDumpFilePath(string $extension, bool $compressed = TRUE): string {
    return self::FIXTURES_DIR . "/terms.$extension" . ($compressed ? '.gz' : '');
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
    exec('gunzip < ' . static::getDumpFilePath('sql') . ' | ' . $mysql);
  }

  /**
   * Synchronizes the locked vocabularies terms from RDFXML dump file.
   */
  private function synchronizeTermsFromRdfXmlFile(): void {
    $graphStore = RdfSyncConnection::createGraphStore();
    $graphUri = $this->configFactory->get('rdf_sync.settings')->get('graph_uri');
    $graph = new Graph($graphUri);
    $graph->parseFile(static::getDumpFilePath('rdf', FALSE));
    $graphStore->insert($graph);
  }

  /**
   * Returns the vocabulary updater service.
   *
   * @return \Drupal\joinup_taxonomy\VocabularyUpdater
   *   The vocabulary updater service.
   */
  private static function getVocabularyUpdater(): VocabularyUpdater {
    if (!isset(self::$vocabularyUpdater)) {
      static::$vocabularyUpdater = \Drupal::service('joinup_taxonomy.vocabulary_updater');
    }
    return self::$vocabularyUpdater;
  }

}
