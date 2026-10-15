<?php

declare(strict_types=1);

namespace Drupal\Tests\sti_dedupe\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\InstallStorage;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\import_ird_jsons\Plugin\migrate\process\IrdClaims;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\sti_dedupe\ContentModel;
use Drupal\sti_rdf\StiUri;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\TermInterface;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Builds the slice of the content model that sti_dedupe works on.
 *
 * Boots core and contrib only, never the Joinup distribution: OG, state
 * machine and the bundle classes are not needed to link solutions or rule on
 * claims, and needing them would be a coupling bug.
 *
 * Fields owned by other modules (solution claims, the claims paragraph,
 * atio_derived_from) are created here with the names and types the site
 * uses. Fields owned by sti_dedupe are installed from its own config/install,
 * so the tests exercise the config that ships, minus rdf_sync mappings: with
 * those in place every save would try to reach a triplestore.
 */
abstract class StiDedupeKernelTestBase extends KernelTestBase {

  use UserCreationTrait;

  /**
   * The collection solutions belong to unless a test names one.
   */
  private ?NodeInterface $defaultCollection = NULL;

  /**
   * The collection-level term imported claim properties sit under.
   */
  private ?TermInterface $propertyParent = NULL;

  protected const READINESS = StiUri::ATIO . 'readinessLevel';
  protected const START_YEAR = StiUri::ATIO . 'startYear';
  protected const INNOVATION_TYPE = StiUri::ATIO . 'innovationType';
  protected const SHORT_DESCRIPTION = StiUri::ATIO . 'shortDescription';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'file',
    'options',
    'node',
    'taxonomy',
    'entity_reference_revisions',
    'paragraphs',
    'serialization',
    'rdf_sync',
    'sti_rdf',
    'sti_dedupe',
  ];

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $container->register('sti_rdf.mapping_reader', TestMappingReader::class);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('paragraph');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('sti_dedupe', ['sti_dedupe_candidate', 'sti_dedupe_decision', 'sti_dedupe_replacement']);
    $this->installConfig(['field', 'filter', 'node']);

    // Revision authorship needs a real account; uid 1 is burnt first so the
    // acting user is an ordinary one.
    $this->createUser();
    $this->setUpCurrentUser([], ['review sti duplicates', 'resolve sti claim conflicts']);

    foreach ([ContentModel::COLLECTION, ContentModel::SOLUTION, ContentModel::INNOVATION] as $type) {
      NodeType::create(['type' => $type, 'name' => $type, 'new_revision' => TRUE])->save();
    }
    foreach ([ContentModel::CLAIM_PROPERTIES, ContentModel::CLAIM_VALUES] as $vid) {
      Vocabulary::create(['vid' => $vid, 'name' => $vid])->save();
    }
    ParagraphsType::create(['id' => ContentModel::CLAIM, 'label' => 'Claims'])->save();

    // As configured on the site by import_ird_jsons and site config.
    $this->field('paragraph', ContentModel::CLAIM, ContentModel::CLAIMED_BY, 'entity_reference', ['target_type' => 'node']);
    $this->field('paragraph', ContentModel::CLAIM, ContentModel::CLAIM_PROPERTY, 'entity_reference', ['target_type' => 'taxonomy_term']);
    $this->field('paragraph', ContentModel::CLAIM, ContentModel::CLAIM_REF_VALUE, 'entity_reference', ['target_type' => 'taxonomy_term']);
    $this->field('paragraph', ContentModel::CLAIM, ContentModel::CLAIM_LITERAL, 'string');
    $this->field('paragraph', ContentModel::CLAIM, 'field_claimed_at', 'timestamp');
    $this->field('node', ContentModel::SOLUTION, ContentModel::CLAIMS, 'entity_reference_revisions', ['target_type' => 'paragraph'], -1);
    $this->field('node', ContentModel::SOLUTION, ContentModel::RELATED_SOLUTIONS, 'entity_reference', ['target_type' => 'node'], -1);
    // OG's group audience, as a plain reference: OG itself is not needed.
    $this->field('node', ContentModel::SOLUTION, ContentModel::AUDIENCE, 'entity_reference', ['target_type' => 'node']);

    // As configured by the atio module.
    $this->field('node', ContentModel::INNOVATION, ContentModel::DERIVED_FROM, 'entity_reference_revisions', ['target_type' => 'node'], -1);
    $this->field('node', ContentModel::INNOVATION, 'atio_readiness_level', 'entity_reference', ['target_type' => 'taxonomy_term']);
    $this->field('node', ContentModel::INNOVATION, 'atio_start_year', 'integer');
    $this->field('node', ContentModel::INNOVATION, 'atio_innovation_type', 'entity_reference', ['target_type' => 'taxonomy_term'], -1);
    $this->field('node', ContentModel::INNOVATION, 'atio_shorter_description', 'string_long');

    $this->installShippedConfig();

    TestMappingReader::$predicates = [
      'node' => [
        ContentModel::INNOVATION => [
          'title' => 'http://purl.org/dc/terms/title',
          'atio_readiness_level' => self::READINESS,
          'atio_start_year' => self::START_YEAR,
          'atio_innovation_type' => self::INNOVATION_TYPE,
          'atio_shorter_description' => self::SHORT_DESCRIPTION,
        ],
      ],
    ];
  }

  /**
   * Installs sti_dedupe's own config/install, without rdf_sync mappings.
   */
  private function installShippedConfig(): void {
    $path = $this->container->get('extension.list.module')->getPath('sti_dedupe') . '/' . InstallStorage::CONFIG_INSTALL_DIRECTORY;
    $source = new FileStorage($path);
    $configManager = $this->container->get('config.manager');

    $names = $source->listAll();
    // Storages, then bundles, then fields: the order their dependencies need.
    usort($names, static fn(string $a, string $b): int => self::installWeight($a) <=> self::installWeight($b) ?: strcmp($a, $b));
    foreach ($names as $name) {
      $record = $source->read($name);
      $entityTypeId = $configManager->getEntityTypeIdByName($name);
      if ($entityTypeId === NULL) {
        $this->config($name)->setData($record)->save();
        continue;
      }
      unset($record['third_party_settings']['rdf_sync']);
      if (isset($record['dependencies']['module'])) {
        $record['dependencies']['module'] = array_values(array_diff($record['dependencies']['module'], ['rdf_sync']));
      }
      $this->container->get('entity_type.manager')->getStorage($entityTypeId)->createFromStorageRecord($record)->save();
    }
  }

  /**
   * Orders config so that dependencies are created first.
   */
  private static function installWeight(string $name): int {
    return match (TRUE) {
      str_starts_with($name, 'paragraphs.paragraphs_type.') => 0,
      str_starts_with($name, 'field.storage.') => 1,
      str_starts_with($name, 'field.field.') => 2,
      default => 3,
    };
  }

  /**
   * Creates a field storage (if needed) and a field.
   */
  protected function field(string $entityType, string $bundle, string $name, string $type, array $storageSettings = [], int $cardinality = 1): void {
    if (FieldStorageConfig::loadByName($entityType, $name) === NULL) {
      FieldStorageConfig::create([
        'field_name' => $name,
        'entity_type' => $entityType,
        'type' => $type,
        'settings' => $storageSettings,
        'cardinality' => $cardinality,
      ])->save();
    }
    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => $entityType,
      'bundle' => $bundle,
    ])->save();
  }

  /**
   * Creates a collection.
   */
  protected function collection(string $title): NodeInterface {
    $node = Node::create(['type' => ContentModel::COLLECTION, 'title' => $title]);
    $node->save();
    return $node;
  }

  /**
   * Creates a collection-scoped claim property, optionally mapped.
   */
  protected function property(string $name, ?string $atioProperty): TermInterface {
    // Under a collection term, as ird_claims builds them: root-level property
    // terms are system predicates.
    if ($this->propertyParent === NULL) {
      $this->propertyParent = Term::create(['vid' => ContentModel::CLAIM_PROPERTIES, 'name' => 'test-collection']);
      $this->propertyParent->save();
    }
    $term = Term::create([
      'vid' => ContentModel::CLAIM_PROPERTIES,
      'name' => $name,
      'parent' => [$this->propertyParent->id()],
    ]);
    if ($atioProperty !== NULL) {
      $term->set(ContentModel::ATIO_PROPERTY, $atioProperty);
    }
    $term->save();
    return $term;
  }

  /**
   * Creates a claim value term, optionally with a SKOS exact match.
   */
  protected function value(string $name, ?string $exactMatch = NULL): TermInterface {
    $term = Term::create(['vid' => ContentModel::CLAIM_VALUES, 'name' => $name]);
    if ($exactMatch !== NULL) {
      $term->set(ContentModel::EXACT_MATCH, $exactMatch);
    }
    $term->save();
    return $term;
  }

  /**
   * Builds an unsaved claim paragraph.
   *
   * @param \Drupal\node\NodeInterface $collection
   *   The claiming collection.
   * @param \Drupal\taxonomy\TermInterface $property
   *   The collection's property term.
   * @param \Drupal\taxonomy\TermInterface|string $value
   *   A value term, or a literal.
   */
  protected function claim(NodeInterface $collection, TermInterface $property, TermInterface|string $value): ParagraphInterface {
    $values = [
      'type' => ContentModel::CLAIM,
      ContentModel::CLAIMED_BY => $collection->id(),
      ContentModel::CLAIM_PROPERTY => $property->id(),
    ];
    if ($value instanceof TermInterface) {
      $values[ContentModel::CLAIM_REF_VALUE] = $value->id();
    }
    else {
      $values[ContentModel::CLAIM_LITERAL] = $value;
    }
    return Paragraph::create($values);
  }

  /**
   * Creates a solution carrying the given claims.
   *
   * @param string $title
   *   The title.
   * @param \Drupal\paragraphs\ParagraphInterface[] $claims
   *   Unsaved claim paragraphs.
   * @param \Drupal\node\NodeInterface|null $collection
   *   The collection holding it; a shared default collection when NULL.
   */
  protected function solution(string $title, array $claims = [], ?NodeInterface $collection = NULL): NodeInterface {
    $this->defaultCollection ??= $this->collection('Default collection');
    $node = Node::create([
      'type' => ContentModel::SOLUTION,
      'title' => $title,
      ContentModel::CLAIMS => $claims,
      ContentModel::AUDIENCE => ($collection ?? $this->defaultCollection)->id(),
    ]);
    $node->save();
    return $node;
  }

  /**
   * Replaces one collection's claims on a solution, the way ird_claims does.
   *
   * Old paragraphs of that collection are deleted and new ones created, so
   * claim paragraph ids change even when the values do not. Claims on a
   * system property are kept, using ird_claims' own rule for telling them
   * apart.
   *
   * @param \Drupal\node\NodeInterface $solution
   *   The solution being re-imported.
   * @param \Drupal\node\NodeInterface $collection
   *   The collection re-importing it.
   * @param \Drupal\paragraphs\ParagraphInterface[] $claims
   *   The collection's fresh, unsaved claims.
   */
  protected function reimport(NodeInterface $solution, NodeInterface $collection, array $claims): NodeInterface {
    $solution = $this->reload($solution);
    $kept = [];
    foreach ($solution->get(ContentModel::CLAIMS)->referencedEntities() as $claim) {
      if ((int) $claim->get(ContentModel::CLAIMED_BY)->target_id === (int) $collection->id() && !IrdClaims::isSystemClaim($claim)) {
        $claim->delete();
      }
      else {
        $kept[] = $claim;
      }
    }
    $solution->set(ContentModel::CLAIMS, array_merge($kept, $claims));
    $solution->save();
    return $solution;
  }

  /**
   * Creates an ATIO innovation derived from the given solutions.
   *
   * @param string $title
   *   The title.
   * @param \Drupal\node\NodeInterface[] $solutions
   *   Its sources, pinned at their current revisions.
   */
  protected function innovation(string $title, array $solutions): NodeInterface {
    $items = array_map(static fn(NodeInterface $solution): array => [
      'target_id' => $solution->id(),
      'target_revision_id' => $solution->getRevisionId(),
    ], $solutions);
    $node = Node::create([
      'type' => ContentModel::INNOVATION,
      'title' => $title,
      'status' => 1,
      ContentModel::DERIVED_FROM => $items,
    ]);
    $node->save();
    return $node;
  }

  /**
   * Loads a node fresh from storage.
   */
  protected function reload(NodeInterface $node): NodeInterface {
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $storage->resetCache([$node->id()]);
    $fresh = $storage->load($node->id());
    $this->assertInstanceOf(NodeInterface::class, $fresh);
    return $fresh;
  }

  /**
   * Returns the solution ids an innovation derives from, sorted.
   *
   * @return int[]
   *   Solution node ids.
   */
  protected function sources(NodeInterface $innovation): array {
    $ids = array_map(static fn(array $item): int => (int) $item['target_id'], $this->reload($innovation)->get(ContentModel::DERIVED_FROM)->getValue());
    sort($ids);
    return $ids;
  }
