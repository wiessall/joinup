<?php

declare(strict_types = 1);

namespace Drupal\joinup_taxonomy\Drush\Commands;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\joinup_taxonomy\VocabularyGraphAlterersTrait;
use Drupal\joinup_taxonomy\VocabularyUpdater;
use Drupal\rdf_sync\RdfSyncConnection;
use Drupal\rdf_sync\RdfSyncMapper;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use EasyRdf\Graph;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Joinup taxonomy Drush commands.
 */
class JoinupTaxonomyCommands extends DrushCommands {

  use VocabularyGraphAlterersTrait;

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
    'ADMS_SKOS_v1.00.rdf' => ['representation_technique', 'status'],
    'business-process.rdf' => ['business_process'],
    'countries-skos.rdf' => ['spatial'],
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
  private const FIXTURES_DIR = DRUPAL_ROOT . '/../resources/vocab';

  /**
   * Static cache of dump file path.
   */
  private static string $dumpFile;

  /**
   * Static cache for vocabulary updater service.
   */
  private static VocabularyUpdater $vocabularyUpdater;

  /**
   * Constructs a new Drush commands class instance.
   *
   * @param \Drupal\rdf_sync\RdfSyncMapper $mapper
   *   The RDF Sync mapper service.
   */
  public function __construct(
    private readonly RdfSyncMapper $mapper,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static($container->get('rdf_sync.mapper'));
  }

  /**
   * Creates locked vocabularies terms.
   */
  #[CLI\Command(name: 'joinup:create-locked-vocabulary-terms')]
  #[CLI\Help(
    description: 'Create terms in locked vocabularies',
    synopsis: 'If there are changes to the RDF files or the MySQL dump file is missing, the command will recreate the MySQL dump file',
  )]
  public function createLockedVocabulariesTermDump(): void {
    if (file_exists($this->getDumpFilePath('sql'))) {
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
   * Synchronizes Drupal terms to RDF backend.
   */
  #[CLI\Command(name: 'joinup:sync-locked-vocabulary-terms')]
  #[CLI\Help(
    description: 'Synchronize Drupal terms to RDF backend',
    synopsis: 'If there are changes to the RDF files or the RDF dump file is missing, the command will recreate the RDF dump file',
  )]
  public function synchronizeTerms(): void {
    if (file_exists($this->getDumpFilePath('rdf', FALSE))) {
      // We already have a valid RDFXML dump file. Restore terms from dump.
      $this->synchronizeTermsFromRdfXmlFile();
      Drush::logger()->notice(dt('✅ synchronized locked vocabularies terms from RDFXML dump'));
      return;
    }

    $vids = array_merge(...array_values(self::VOCABS));

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

    $graph = RdfSyncConnection::createGraphStore()->get(RdfSyncConnection::GRAPH);

    $vocabularyUris = array_map(function (string $vid): string {
      return $this->mapper->getBundleRdfType('taxonomy_term', $vid);
    }, $vids);
    foreach ($graph->resources() as $resource) {
      // Cleanup potential triples which are not part of the vocabularies.
      if (!$resource->isA('skos:Concept') || !in_array($resource->getResource('skos:inScheme')->getUri(), $vocabularyUris, TRUE)) {
        $graph->deleteEntireResource($resource);
      }
    }

    // Generate the RDFXML dump file.
    $file = static::getDumpFilePath('rdf', FALSE);
    file_put_contents($file, $graph->serialise('rdfxml'));

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
    $vids = [];
    foreach (\Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary')->loadMultiple() as $vid => $vocabulary) {
      if ($type = $mapper->getBundleRdfType('taxonomy_term', $vid)) {
        $vids[$type] = $vid;
      }
    }

    $context['results']['vids'] = $vids;
    $context['results']['pattern'] = '@' . implode('|', array_map('preg_quote', array_keys($vids))) . '@';
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
    $content = file_get_contents(self::FIXTURES_DIR . '/' . $file);

    // Bird eye check to avoid heavy graph parsing.
    if (!preg_match($context['results']['pattern'], $content)) {
      $context['message'] = dt('❌ no destination vocabulary');
      return;
    }

    $graph = new Graph();
    $graph->parse($content);
    $graphUri = 'http://' . strtolower(pathinfo($file, PATHINFO_FILENAME));

    $callbacks = [];
    foreach (self::VOCABS[$file] as $vid) {
      $rdfType = array_flip($context['results']['vids'])[$vid];
      self::getVocabularyUpdater()->registerVocabulary($vid, $rdfType, $graphUri);

      $callbacks[] = [self::class, 'prepareGraph'];
      // Allow specific vocabulary alters.
      $method = 'alterGraphFor' . ContainerBuilder::camelize($vid);
      if (method_exists(static::class, $method)) {
        $callbacks[] = [self::class, $method];
      }
    }

    $results = self::getVocabularyUpdater()
      // Pass the already collected, URI <-> TID mappings to resolve
      // dependencies between terms from different vocabularies.
      ->setUriToTid($context['results']['uri_to_tid'])
      ->registerGraph($graphUri, $graph, $callbacks)
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
  private static function getDumpFilePath(string $extension, bool $compressed = TRUE): string {
    if (!isset(static::$dumpFile)) {
      $checksum = '';
      foreach (array_keys(self::VOCABS) as $file) {
        $checksum .= sha1_file(self::FIXTURES_DIR . '/' . $file);
      }
      $checksum = Crypt::hashBase64($checksum);
      static::$dumpFile = self::FIXTURES_DIR . "/terms.$checksum.$extension";
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
    exec('gunzip < ' . static::getDumpFilePath('sql') . ' | ' . $mysql);
  }

  /**
   * Synchronizes the locked vocabularies terms from RDFXML dump file.
   */
  private function synchronizeTermsFromRdfXmlFile(): void {
    $graphStore = RdfSyncConnection::createGraphStore();
    $graph = new Graph(RdfSyncConnection::GRAPH);
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
