<?php

declare(strict_types=1);

namespace Drupal\sti_rdf\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\ConditionInterface;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\sti_rdf\StiUri;

/**
 * Moves STI entities from pre-P0 URIs to https://sti-portal.fao.org/id/….
 *
 * Two steps, always in this order:
 *
 * 1. Bundle config: every bundle on the bare 'sti_namespace' plugin, or on
 *    Joinup's 'joinup_po_namespace:w21', is switched to an
 *    'sti_namespace:{type}' derivative. Bare 'sti_namespace' stops being a
 *    valid plugin id when the deriver is introduced, so this must run before
 *    any mapped entity is saved again.
 * 2. Stored URIs: rows in rdf_sync_uri, and values of stored URI fields
 *    (uri_field_name), that still carry a legacy prefix are rewritten to the
 *    new pattern. Externally assigned URIs (an AGROVOC or UN URI in term_uri)
 *    are left alone, because only legacy prefixes are touched.
 *
 * This writes to rdf_sync_uri directly. rdf_sync has no API for changing the
 * URI of an existing entity: the URI is minted once, on first sync, and then
 * read from that table. A full re-sync after this runs rebuilds the graph
 * under the new URIs; the old graph has to be dropped first, because rdf_sync
 * deletes triples by the URI it currently knows and therefore cannot find
 * triples written under the old one.
 *
 * Failure here is silent by nature (a row left behind just keeps its old
 * URI), so verify() checks the outcome and the update hook fails loudly on a
 * non-empty result.
 */
final class UriReminter {

  /**
   * Instance type per bundle, for bundles not on a typed plugin yet.
   *
   * Vocabularies are not listed: every vocabulary on the bare plugin becomes
   * a 'concept', except the two claim vocabularies, which are listed because
   * they had no plugin at all and their terms had no URI.
   */
  public const MIGRATION_MAP = [
    'node' => [
      'solution' => 'source-record',
      'atio_innovation' => 'innovation',
      'core_annotation' => 'annotation',
      'collection' => 'collection',
      'owner' => 'agent',
      // sti_rdf's leftover from the solution rename.
      'innovation' => 'source-record',
      'dah_annotation' => 'annotation',
    ],
    'paragraph' => [
      'claims' => 'claim',
    ],
    'taxonomy_term' => [
      'claim_properties' => 'property',
      'claim_values' => 'concept',
    ],
  ];

  /**
   * Plugin ids that are replaced by an sti_namespace derivative.
   */
  private const LEGACY_PLUGINS = ['sti_namespace', 'joinup_po_namespace:w21'];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFieldManagerInterface $entityFieldManager,
    private readonly Connection $database,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly LoggerChannelInterface $logger,
  ) {}

  /**
   * Lists the bundle config changes step 1 would make.
   *
   * @return list<array{entity_type: string, bundle: string, from: string|null, to: string}>
   *   One entry per bundle whose uri_plugin changes.
   *
   * @throws \UnexpectedValueException
   *   When a node or paragraph bundle is on a legacy plugin but has no entry
   *   in MIGRATION_MAP. Guessing a type would freeze a wrong URI, so this is
   *   loud on purpose: add the bundle to the map.
   */
  public function planConfigChanges(): array {
    $plan = [];
    foreach ($this->mappedBundleEntities() as [$entityTypeId, $bundleEntity]) {
      $bundle = (string) $bundleEntity->id();
      $current = $bundleEntity->getThirdPartySetting('rdf_sync', 'uri_plugin');
      $mapped = self::MIGRATION_MAP[$entityTypeId][$bundle] ?? NULL;

      $isLegacy = in_array($current, self::LEGACY_PLUGINS, TRUE);
      // The claim vocabularies have no plugin at all yet.
      $needsPlugin = $current === NULL && $mapped !== NULL;
      if (!$isLegacy && !$needsPlugin) {
        continue;
      }

      if ($mapped === NULL) {
        if ($entityTypeId !== 'taxonomy_term') {
          throw new \UnexpectedValueException(sprintf('Bundle %s:%s uses the legacy URI plugin "%s" but has no instance type in UriReminter::MIGRATION_MAP.', $entityTypeId, $bundle, $current));
        }
        $mapped = 'concept';
      }

      $plan[] = [
        'entity_type' => $entityTypeId,
        'bundle' => $bundle,
        'from' => $current,
        'to' => 'sti_namespace:' . $mapped,
      ];
    }
    return $plan;
  }

  /**
   * Step 1: switches bundle config to sti_namespace derivatives.
   *
   * Written through the config factory rather than by saving the bundle
   * entity. Saving the entity would fire entity hooks that react to a
   * changed mapping, mid-update and before any URI has been rewritten; only
   * the one key changes here, and the config dependencies stay as they are
   * because the bundle already depends on rdf_sync.
   *
   * @return int
   *   The number of bundles changed.
   */
  public function applyConfigChanges(): int {
    $changed = 0;
    foreach ($this->planConfigChanges() as $change) {
      $bundleEntity = $this->bundleEntity($change['entity_type'], $change['bundle']);
      if ($bundleEntity === NULL) {
        continue;
      }
      $this->configFactory->getEditable($bundleEntity->getConfigDependencyName())
        ->set('third_party_settings.rdf_sync.uri_plugin', $change['to'])
        ->save();
      $this->entityTypeManager->getStorage((string) $bundleEntity->getEntityTypeId())->resetCache([$change['bundle']]);
      $this->logger->notice('Switched @type:@bundle from @from to @to.', [
        '@type' => $change['entity_type'],
        '@bundle' => $change['bundle'],
        '@from' => $change['from'] ?? '(none)',
        '@to' => $change['to'],
      ]);
      $changed++;
    }
    return $changed;
  }

  /**
   * Lists the units of work for step 2.
   *
   * Call after applyConfigChanges(): the type of each bundle is read from its
   * (now typed) uri_plugin.
   *
   * @return list<array{entity_type: string, bundle: string, type: string, field: string|null}>
   *   'field' is NULL for rdf_sync_uri rows, or the name of a stored URI
   *   field whose values are rewritten as well.
   */
  public function scopes(): array {
    $scopes = [];
    foreach ($this->mappedBundleEntities() as [$entityTypeId, $bundleEntity]) {
      $plugin = (string) $bundleEntity->getThirdPartySetting('rdf_sync', 'uri_plugin');
      if (!str_starts_with($plugin, 'sti_namespace:')) {
        continue;
      }
      $type = substr($plugin, strlen('sti_namespace:'));
      $bundle = (string) $bundleEntity->id();
      $scopes[] = ['entity_type' => $entityTypeId, 'bundle' => $bundle, 'type' => $type, 'field' => NULL];

      $uriField = $bundleEntity->getThirdPartySetting('rdf_sync', 'uri_field_name');
      if (is_string($uriField) && $this->storedFieldStorage($entityTypeId, $uriField) !== NULL) {
        $scopes[] = ['entity_type' => $entityTypeId, 'bundle' => $bundle, 'type' => $type, 'field' => $uriField];
      }
    }
    return $scopes;
  }

  /**
   * Step 2: rewrites one batch of legacy URIs within one scope.
   *
   * @param array{entity_type: string, bundle: string, type: string, field: string|null} $scope
   *   A scope from scopes().
   * @param int $afterId
   *   Process entity ids greater than this; 0 to start.
   * @param int $limit
   *   Maximum number of entities to examine.
   *
   * @return array{last_id: int, examined: int, rewritten: int}
   *   'last_id' is the cursor for the next call; 'examined' is 0 when the
   *   scope is exhausted.
   */
  public function remintBatch(array $scope, int $afterId, int $limit = 500): array {
    return $scope['field'] === NULL
      ? $this->remintRowsBatch($scope, $afterId, $limit)
      : $this->remintFieldBatch($scope, $afterId, $limit);
  }

  /**
   * Counts URIs still carrying a legacy prefix, per scope.
   *
   * @return array<string, int>
   *   Keyed "entity_type:bundle" or "entity_type:bundle:field"; only scopes
   *   with a non-zero count are returned.
   */
  public function verify(): array {
    $left = [];
    foreach ($this->scopes() as $scope) {
      $count = $scope['field'] === NULL
        ? $this->countLegacyRows($scope['entity_type'], $scope['bundle'])
        : $this->countLegacyFieldValues($scope);
      if ($count > 0) {
        $key = $scope['entity_type'] . ':' . $scope['bundle'] . ($scope['field'] ? ':' . $scope['field'] : '');
        $left[$key] = $count;
      }
    }
    return $left;
  }

  /**
   * Rewrites legacy rdf_sync_uri rows for one bundle.
   */
  private function remintRowsBatch(array $scope, int $afterId, int $limit): array {
    $entityType = $this->entityTypeManager->getDefinition($scope['entity_type']);
    $storage = $this->entityTypeManager->getStorage($scope['entity_type']);
    assert($storage instanceof SqlEntityStorageInterface);
    $idKey = (string) $entityType->getKey('id');
    $uuidKey = (string) $entityType->getKey('uuid');

    $query = $this->database->select('rdf_sync_uri', 'r');
    $query->innerJoin($storage->getBaseTable(), 'b', "b.$idKey = r.entity_id");
    $query->fields('r', ['entity_id', 'uri']);
    $query->addField('b', $uuidKey, 'uuid');
    $query->condition('r.entity_type', $scope['entity_type'])
      ->condition('r.bundle', $scope['bundle'])
      ->condition('r.entity_id', $afterId, '>')
      ->orderBy('r.entity_id')
      ->range(0, $limit);

    $examined = 0;
    $rewritten = 0;
    $lastId = $afterId;
    foreach ($query->execute() as $row) {
      $examined++;
      $lastId = (int) $row->entity_id;
      if (!StiUri::isLegacy((string) $row->uri)) {
        continue;
      }
      $this->database->update('rdf_sync_uri')
        ->fields(['uri' => StiUri::forEntity($scope['type'], (string) $row->uuid)])
        ->condition('entity_type', $scope['entity_type'])
        ->condition('entity_id', $row->entity_id)
        ->execute();
      $rewritten++;
    }

    return ['last_id' => $lastId, 'examined' => $examined, 'rewritten' => $rewritten];
  }

  /**
   * Rewrites legacy values of a stored URI field for one bundle.
   *
   * Both the data and the revision table are updated, so that a revert to an
   * older revision does not bring a legacy URI back.
   */
  private function remintFieldBatch(array $scope, int $afterId, int $limit): array {
    $fieldStorage = $this->storedFieldStorage($scope['entity_type'], (string) $scope['field']);
    if ($fieldStorage === NULL) {
      return ['last_id' => $afterId, 'examined' => 0, 'rewritten' => 0];
    }
    [$dataTable, $revisionTable, $column] = $this->fieldTables($scope['entity_type'], $fieldStorage);

    $entityType = $this->entityTypeManager->getDefinition($scope['entity_type']);
    $storage = $this->entityTypeManager->getStorage($scope['entity_type']);
    assert($storage instanceof SqlEntityStorageInterface);
    $idKey = (string) $entityType->getKey('id');
    $uuidKey = (string) $entityType->getKey('uuid');

    $query = $this->database->select($dataTable, 'f');
    $query->innerJoin($storage->getBaseTable(), 'b', "b.$idKey = f.entity_id");
    $query->fields('f', ['entity_id', $column]);
    $query->addField('b', $uuidKey, 'uuid');
    $query->condition('f.bundle', $scope['bundle'])
      ->condition('f.entity_id', $afterId, '>')
      ->orderBy('f.entity_id')
      ->range(0, $limit);

    $examined = 0;
    $rewritten = 0;
    $lastId = $afterId;
    foreach ($query->execute() as $row) {
      $examined++;
      $lastId = (int) $row->entity_id;
      $old = (string) $row->{$column};
      if (!StiUri::isLegacy($old)) {
        continue;
      }
      $new = StiUri::forEntity($scope['type'], (string) $row->uuid);
      foreach (array_filter([$dataTable, $revisionTable]) as $table) {
        $this->database->update($table)
          ->fields([$column => $new])
          ->condition('entity_id', $row->entity_id)
          ->condition($column, $old)
          ->execute();
      }
      $rewritten++;
    }

    return ['last_id' => $lastId, 'examined' => $examined, 'rewritten' => $rewritten];
  }

  /**
   * Counts legacy rows in rdf_sync_uri for one bundle.
   */
  private function countLegacyRows(string $entityTypeId, string $bundle): int {
    $query = $this->database->select('rdf_sync_uri', 'r')
      ->condition('r.entity_type', $entityTypeId)
      ->condition('r.bundle', $bundle);
    $query->condition($this->legacyCondition($query, 'r.uri'));
    return (int) $query->countQuery()->execute()->fetchField();
  }

  /**
   * Counts legacy values of a stored URI field for one bundle.
   */
  private function countLegacyFieldValues(array $scope): int {
    $fieldStorage = $this->storedFieldStorage($scope['entity_type'], (string) $scope['field']);
    if ($fieldStorage === NULL) {
      return 0;
    }
    [$dataTable, , $column] = $this->fieldTables($scope['entity_type'], $fieldStorage);
    $query = $this->database->select($dataTable, 'f')
      ->condition('f.bundle', $scope['bundle']);
    $query->condition($this->legacyCondition($query, "f.$column"));
    return (int) $query->countQuery()->execute()->fetchField();
  }

  /**
   * Builds "column LIKE prefix1% OR column LIKE prefix2% …".
   */
  private function legacyCondition(SelectInterface $query, string $column): ConditionInterface {
    $group = $query->orConditionGroup();
    foreach (StiUri::LEGACY_PREFIXES as $prefix) {
      $group->condition($column, $this->database->escapeLike($prefix) . '%', 'LIKE');
    }
    return $group;
  }

  /**
   * Returns the data table, revision table and value column of a field.
   *
   * @return array{0: string, 1: string|null, 2: string}
   *   The tables and the column holding the field's main property.
   */
  private function fieldTables(string $entityTypeId, FieldStorageDefinitionInterface $fieldStorage): array {
    $storage = $this->entityTypeManager->getStorage($entityTypeId);
    assert($storage instanceof SqlEntityStorageInterface);
    $mapping = $storage->getTableMapping();
    $dataTable = $mapping->getDedicatedDataTableName($fieldStorage);
    $revisionTable = $this->entityTypeManager->getDefinition($entityTypeId)->isRevisionable()
      ? $mapping->getDedicatedRevisionTableName($fieldStorage)
      : NULL;
    if ($revisionTable !== NULL && !$this->database->schema()->tableExists($revisionTable)) {
      $revisionTable = NULL;
    }
    $column = $mapping->getFieldColumnName($fieldStorage, (string) $fieldStorage->getMainPropertyName());
    return [$dataTable, $revisionTable, $column];
  }

  /**
   * Returns a field's storage definition if it is a stored, configurable field.
   *
   * Computed fields with custom storage, which is how rdf_sync provides
   * uri_field_name when no real field exists, keep their value in
   * rdf_sync_uri and are covered by the row scope instead.
   */
  private function storedFieldStorage(string $entityTypeId, string $fieldName): ?FieldStorageDefinitionInterface {
    $definitions = $this->entityFieldManager->getFieldStorageDefinitions($entityTypeId);
    $definition = $definitions[$fieldName] ?? NULL;
    if ($definition === NULL || $definition->hasCustomStorage() || $definition->isBaseField()) {
      return NULL;
    }
    return $definition;
  }

  /**
   * Yields every bundle config entity that carries an rdf_sync mapping.
   *
   * @return iterable<array{0: string, 1: \Drupal\Core\Config\Entity\ConfigEntityInterface}>
   *   Content entity type id and bundle config entity.
   */
  private function mappedBundleEntities(): iterable {
    foreach (array_keys(self::MIGRATION_MAP) as $entityTypeId) {
      if (!$this->entityTypeManager->hasDefinition($entityTypeId)) {
        continue;
      }
      $bundleEntityType = $this->entityTypeManager->getDefinition($entityTypeId)->getBundleEntityType();
      if ($bundleEntityType === NULL) {
        continue;
      }
      foreach ($this->entityTypeManager->getStorage($bundleEntityType)->loadMultiple() as $bundleEntity) {
        if ($bundleEntity instanceof ConfigEntityInterface && $bundleEntity->getThirdPartySetting('rdf_sync', 'type') !== NULL) {
          yield [$entityTypeId, $bundleEntity];
        }
      }
    }
  }

  /**
   * Loads one bundle config entity.
   */
  private function bundleEntity(string $entityTypeId, string $bundle): ?ConfigEntityInterface {
    $bundleEntityType = $this->entityTypeManager->getDefinition($entityTypeId)->getBundleEntityType();
    $entity = $bundleEntityType ? $this->entityTypeManager->getStorage($bundleEntityType)->load($bundle) : NULL;
    return $entity instanceof ConfigEntityInterface ? $entity : NULL;
  }

}
