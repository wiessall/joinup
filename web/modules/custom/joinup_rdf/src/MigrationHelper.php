<?php

declare(strict_types=1);

namespace Drupal\joinup_rdf;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_rdf\Entity\RdfSyncEntityInterface;
use Drupal\joinup_taxonomy\Entity\MappedTerm;
use Drupal\og\OgGroupAudienceHelperInterface;
use Drupal\path_alias\AliasRepositoryInterface;
use Drupal\pathauto\PathautoState;
use Drupal\rdf_sync\RdfSyncConnectionInterface;
use Drupal\rdf_sync\RdfSyncMapper;

/**
 * Helper class for the RDF2Node migration.
 *
 * @todo Remove this service in ISAICP-8376.
 */
final class MigrationHelper {

  public const array GROUP_REF_TABLES = [
    ['node', 'field_shared_in', TRUE],
    ['node', 'field_news_referenced_solution', TRUE],
    ['node', 'og_audience', TRUE],
    ['media', 'og_audience', TRUE],
    ['ogmenu_instance', 'og_audience', FALSE],
  ];

  public const array DEDICATED_TABLE_COLUMNS = [
    'bundle',
    'deleted',
    'entity_id',
    'revision_id',
    'langcode',
    'delta',
  ];

  public const array URL_ENCODE_MAP = [
    '_' => '_a',
    '-' => '_b',
    '.' => '_c',
    '~' => '_d',
    ':' => '_e',
    '/' => '_f',
    '?' => '_g',
    '#' => '_h',
    '[' => '_i',
    ']' => '_j',
    '@' => '_k',
    '!' => '_l',
    '$' => '_m',
    '&' => '_n',
    '\'' => '_o',
    '(' => '_p',
    ')' => '_q',
    '*' => '_r',
    '+' => '_s',
    ',' => '_t',
    ';' => '_u',
    '=' => '_v',
    '`' => '_w',
    '%' => '_x',
  ];

  /**
   * Used as SQL expression that decodes an URI.
   *
   * Constant value computed with this code snippet:
   * @code
   * $expression = ["%s, "];
   * foreach (array_reverse(self::URL_ENCODE_MAP) as $key => $value) {
   *   array_unshift($expression, "REPLACE(");
   *   $expression[] = "'$value', '" . addslashes($key) . "'), ";
   * }
   * return trim(implode('', $expression), ',');
   * @endcode
   *
   * @see self::sqlExpressionUriDecode()
   */
  private const string URL_DECODE_SQL_EXPRESSION = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(%s, '_x', '%%'), '_w', '`'), '_v', '='), '_u', ';'), '_t', ','), '_s', '+'), '_r', '*'), '_q', ')'), '_p', '('), '_o', '\''), '_n', '&'), '_m', '$'), '_l', '!'), '_k', '@'), '_j', ']'), '_i', '['), '_h', '#'), '_g', '?'), '_f', '/'), '_e', ':'), '_d', '~'), '_c', '.'), '_b', '-'), '_a', '_')";

  /**
   * Used as SQL expression that decodes an URI.
   *
   * Constant value computed with this code snippet:
   * @code
   * $expression = ["%s, "];
   * foreach (MigrationHelper::URL_ENCODE_MAP as $key => $value) {
   *   array_unshift($expression, "REPLACE(");
   *   $expression[] = "'" . addslashes($key) . "', '$value'), ";
   * }
   * $return = trim(implode('', $expression), ',');
   * @endcode
   *
   * @see self::sqlExpressionUriEncode()
   */
  private const string URL_ENCODE_SQL_EXPRESSION = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(%s, '_', '_a'), '-', '_b'), '.', '_c'), '~', '_d'), ':', '_e'), '/', '_f'), '?', '_g'), '#', '_h'), '[', '_i'), ']', '_j'), '@', '_k'), '!', '_l'), '$', '_m'), '&', '_n'), '\'', '_o'), '(', '_p'), ')', '_q'), '*', '_r'), '+', '_s'), ',', '_t'), ';', '_u'), '=', '_v'), '`', '_w'), '%%', '_x')";

  /**
   * Mapping of fields from the RDF to the node.
   */
  private const array FIELD_MAPPING = [
    'collection' => [
      'http://purl.org/dc/terms/modified' => [
        'target_field_name' => 'changed',
        'format' => 'datetime',
      ],
      'http://purl.org/dc/terms/title' => [
        'target_field_name' => 'title',
        'format' => 'string',
        'trim' => TRUE,
      ],
      'http://joinup.eu/language' => [
        'target_field_name' => 'langcode',
        'format' => 'string',
      ],
      'http://joinup.eu/language/default' => [
        'target_field_name' => 'default_langcode',
        'format' => 'string',
      ],
      'http://purl.org/dc/terms/description' => [
        'target_field_name' => 'description',
        'format' => 'string',
        'default_values' => [
          'format' => 'content_editor',
        ],
      ],
      'http://joinup.eu/owner/uid' => [
        'target_field_name' => 'uid',
        'format' => 'reference',
      ],
      'http://purl.org/dc/terms/issued' => [
        'target_field_name' => 'created',
        'format' => 'datetime',
      ],
      // There is 1 case where the logo has a different value.
      'http://xmlns.com/foaf/0.1/#term_Image' => [
        'target_field_name' => 'logo',
        'format' => 'reference',
      ],
      'http://joinup.eu/search' => [
        'target_field_name' => 'listing_content',
        'format' => 'string',
        'serialized' => TRUE,
      ],
      'http://purl.org/dc/dcmitype/StillImage' => [
        'target_field_name' => 'logo',
        'format' => 'reference',
      ],
      'http://purl.org/dc/terms/publisher' => [
        'target_field_name' => 'owner',
        'format' => 'reference',
        'mapped_term' => TRUE,
      ],
      'http://www.w3.org/ns/dcat#keyword' => [
        'target_field_name' => 'field_keywords',
        'format' => 'reference',
        'mapped_term' => TRUE,
      ],
      'http://joinup.eu/site-featured' => [
        'target_field_name' => 'site_featured',
        'format' => 'string',
      ],
      'http://purl.org/dc/terms/abstract' => [
        'target_field_name' => 'abstract',
        'format' => 'string',
        'default_values' => [
          'format' => 'content_editor',
        ],
      ],
      'http://purl.org/dc/terms/RightsStatement' => [
        'target_field_name' => 'closed_group',
        'format' => 'string',
      ],
      'http://joinup.eu/collection/moderation' => [
        'target_field_name' => 'group_moderation',
        'format' => 'string',
      ],
      'http://joinup.eu/ar/workflow_status' => [
        'target_field_name' => 'field_state',
        'format' => 'string',
      ],
      'http://xmlns.com/foaf/spec/#term_homepage' => [
        'target_field_name' => 'website_url',
        'format' => 'string',
      ],
      'http://purl.org/dc/terms/spatial' => [
        'target_field_name' => 'spatial_coverage',
        'format' => 'reference',
        'mapped_term' => TRUE,
      ],
      'http://www.w3.org/ns/dcat#contactPoint' => [
        'target_field_name' => 'contact',
        'format' => 'reference',
        'mapped_term' => TRUE,
      ],
      'http://joinup.eu/collection/published-at' => [
        'target_field_name' => 'published_at',
        'format' => 'datetime',
      ],
      'http://purl.org/dc/terms/alternative' => [
        'target_field_name' => 'short_id',
        'format' => 'string',
      ],
      'http://joinup.eu/collection/content_creation' => [
        'target_field_name' => 'content_creation',
        'format' => 'string',
      ],
      'http://joinup.eu/vocabulary/topic' => [
        'target_field_name' => 'field_topic',
        'format' => 'reference',
        'mapped_term' => TRUE,
      ],
    ],
    'solution' => [
      'http://purl.org/dc/terms/modified' => [
        'target_field_name' => 'changed',
        'format' => 'datetime',
      ],
      'http://purl.org/dc/terms/title' => [
        'target_field_name' => 'title',
        'format' => 'string',
        'trim' => TRUE,
      ],
      'http://joinup.eu/language' => [
        'target_field_name' => 'langcode',
        'format' => 'string',
      ],
      'http://joinup.eu/language/default' => [
        'target_field_name' => 'default_langcode',
        'format' => 'string',
      ],
      'http://joinup.eu/owner/uid' => [
        'target_field_name' => 'uid',
        'format' => 'reference',
      ],
      'http://purl.org/dc/terms/issued' => [
        'target_field_name' => 'created',
        'format' => 'datetime',
      ],
      'http://purl.org/dc/terms/abstract' => [
        'target_field_name' => 'abstract',
        'format' => 'string',
        'default_values' => [
          'format' => 'content_editor',
        ],
      ],
      'http://joinup.eu/collection/published-at' => [
        'target_field_name' => 'published_at',
        'format' => 'datetime',
      ],
      'http://www.w3.org/ns/dcat#contactPoint' => [
        'target_field_name' => 'contact',
        'format' => 'reference',
      ],
      'http://joinup.eu/search' => [
        'target_field_name' => 'listing_content',
        'format' => 'string',
        'serialized' => TRUE,
      ],
      'http://joinup.eu/solution/content_creation' => [
        'target_field_name' => 'content_creation',
        'format' => 'string',
      ],
      'http://purl.org/dc/terms/description' => [
        'target_field_name' => 'description',
        'format' => 'string',
        'default_values' => [
          'format' => 'content_editor',
        ],
      ],
      'http://xmlns.com/foaf/0.1/page' => [
        'target_field_name' => 'documentation',
        'format' => 'string',
      ],
      'http://www.w3.org/ns/dcat#landingPage' => [
        'target_field_name' => 'landing_page',
        'format' => 'string',
      ],
      'http://purl.org/dc/terms/language' => [
        'target_field_name' => 'language',
        'format' => 'reference',
      ],
      'http://purl.org/dc/dcmitype/StillImage' => [
        // @todo Create a follow up to merge all logo fields into one.
        'target_field_name' => 'logo',
        'format' => 'reference',
      ],
      // @todo Do we need it? Minimal data exist.
      // @codingStandardsIgnoreStart
      // select * where { GRAPH ?g { ?s <http://joinup.eu/solution/metrics_page> ?o } }
      // @codingStandardsIgnoreEnd
      'http://joinup.eu/solution/metrics_page' => [
        'target_field_name' => 'metrics_page',
        'format' => 'uri',
      ],
      'http://joinup.eu/solution/moderation' => [
        'target_field_name' => 'group_moderation',
        'format' => 'string',
      ],
      'http://purl.org/dc/terms/publisher' => [
        'target_field_name' => 'owner',
        'format' => 'reference',
      ],
      'http://purl.org/dc/terms/relation' => [
        'target_field_name' => 'related_solutions',
        'format' => 'reference',
        'mapped_term' => TRUE,
      ],
      // @todo Create a follow up to merge field_shared_in with this one.
      // @todo We are going to need to reuse the same reference handler as we did
      //   for distributions so that the `field_shared_in` can accommodate the
      //   solution parents.
      'http://joinup.eu/solution/shared_in' => [
        'target_field_name' => 'shared_in',
        'format' => 'reference',
        'mapped_term' => TRUE,
      ],
      // @todo Ensure we have given up on EIRA related entities and that the field
      //   should be removed. However, this might be more relevant when the
      //   vocabulary is optimized
      'http://joinup.eu/solution/related_by_type' => [
        'target_field_name' => 'list_related_entities',
        'format' => 'string',
      ],
      'http://purl.org/dc/terms/type' => [
        'target_field_name' => 'solution_type',
        'format' => 'reference',
        'mapped_term' => TRUE,
      ],
      'http://joinup.eu/is/workflow_status' => [
        'target_field_name' => 'field_state',
        'format' => 'string',
      ],
      // @todo We don't seem to use this field but minimal data exist.
      // @codingStandardsIgnoreStart
      // select * where { GRAPH ?g { ?s <http://www.w3.org/ns/adms#translation> ?o } }
      // @codingStandardsIgnoreEnd
      'http://www.w3.org/ns/adms#translation' => [
        'target_field_name' => 'translation',
        'format' => 'reference',
      ],
      // @todo Minimal old data exist.
      // @codingStandardsIgnoreStart
      // select * where { GRAPH ?g { ?s <http://www.w3.org/2002/07/owl#versionInfo> ?o } VALUES ?g { <http://joinup.eu/solution/published> <http://joinup.eu/solution/draft> } }
      // @codingStandardsIgnoreEnd
      'http://www.w3.org/2002/07/owl#versionInfo' => [
        'target_field_name' => 'release_number',
        'format' => 'string',
      ],
      'http://www.w3.org/ns/dcat#keyword' => [
        'target_field_name' => 'field_keywords',
        'format' => 'reference',
        'mapped_term' => TRUE,
      ],
      'http://purl.org/dc/terms/alternative' => [
        'target_field_name' => 'short_id',
        'format' => 'string',
      ],
      'http://joinup.eu/site-featured' => [
        'target_field_name' => 'site_featured',
        'format' => 'string',
      ],
      'http://purl.org/dc/terms/spatial' => [
        'target_field_name' => 'spatial_coverage',
        'format' => 'reference',
        'mapped_term' => TRUE,
      ],
      'http://www.w3.org/ns/adms#status' => [
        'target_field_name' => 'process_status',
        'format' => 'string',
      ],
      'http://joinup.eu/vocabulary/topic' => [
        'target_field_name' => 'field_topic',
        'format' => 'reference',
        'mapped_term' => TRUE,
      ],
    ],
  ];

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AliasRepositoryInterface $pathAliasRepository,
    protected Connection $connection,
    protected RdfSyncConnectionInterface $sparqlEndpoint,
    protected RdfSyncMapper $rdfSyncMapper,
  ) {}

  /**
   * Migrates a group.
   *
   * @param string $type
   *   Could be 'collection' or 'solution'.
   * @param string $uri
   *   The URI of the group.
   * @param int|null $collectionNid
   *   (optional) Only for solutions. The parent collection node ID.
   */
  public function migrateGroup(string $type, string $uri, ?int $collectionNid = NULL): void {
    $group = $this->getGroup($type, $uri, $collectionNid);
    $isNew = TRUE;

    $values = $this->getEntityData($uri, "http://joinup.eu/$type/published");
    if (!empty($values)) {
      $this->massageValues($group, $values);
      $this->save($group);
      $isNew = FALSE;
    }

    // Handle the draft content. If the entity has not been saved, it is an
    // unpublished entity, otherwise, it is a new revision.
    $values = $this->getEntityData($uri, "http://joinup.eu/$type/draft");
    if (!empty($values)) {
      $this->massageValues($group, $values);
      if ($isNew) {
        $this->save($group);
      }
      else {
        $group->setRevisionCreationTime($values['http://purl.org/dc/terms/modified'][0]->getTimestamp());
        $group->setRevisionUserId($values['http://joinup.eu/owner/uid'][0]);
        $group->setRevisionTranslationAffected(TRUE);
        $group->setNewRevision();
        $group->skip_notification = TRUE;
        $group->save();
      }
    }
  }

  /**
   * Returns the initial group.
   *
   * @param string $type
   *   Could be 'collection' or 'solution'.
   * @param string $uri
   *   The URI of the group.
   * @param int|null $collectionNid
   *   Only for solutions. The parent collection node ID.
   *
   * @return \Drupal\joinup_group\Entity\GroupInterface
   *   The initial group entity.
   */
  private function getGroup(string $type, string $uri, ?int $collectionNid): GroupInterface {
    assert(in_array($type, ['solution', 'collection'], TRUE));
    if ($type === 'collection') {
      return $this->createGroup($type, $uri);
    }
    // Because we have migrated solution stubs in the release migration, we
    // need to check if the entity exists already first.
    if (!$group = $this->rdfSyncMapper->getEntityByUri($uri)) {
      $group = $this->createGroup($type, $uri);
    }
    return $group->set(OgGroupAudienceHelperInterface::DEFAULT_FIELD, $collectionNid);
  }

  /**
   * Creates a new group with ony the URI set.
   *
   * @param string $type
   *   Could be 'collection' or 'solution'.
   * @param string $uri
   *   The URI of the group.
   *
   * @return \Drupal\joinup_group\Entity\GroupInterface
   *   The group.
   */
  private function createGroup(string $type, string $uri): GroupInterface {
    $values = ['type' => $type, 'uri' => $uri];

    // Try to preserve the UUID from URI.
    if (str_starts_with($uri, 'http://data.europa.eu/w21/')) {
      $uuid = str_replace('http://data.europa.eu/w21/', '', $uri);
      if (preg_match('/^[0-9a-fA-F]{8}\b-[0-9a-fA-F]{4}\b-[0-9a-fA-F]{4}\b-[0-9a-fA-F]{4}\b-[0-9a-fA-F]{12}$/', $uuid)) {
        $values['uuid'] = $uuid;
      }
    }

    $group = $this->entityTypeManager->getStorage('node')->create($values);
    assert($group instanceof GroupInterface);
    return $group;
  }

  /**
   * Sets the group node fields.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface $group
   *   The entity to set the fields.
   * @param array $values
   *   The values to set.
   */
  protected function massageValues(GroupInterface $group, array $values): void {
    foreach (self::FIELD_MAPPING[$group->bundle()] as $property => $mapping) {
      $value = $values[$property] ?? NULL;
      if ($value) {
        $value = $this->parseValue($value, $mapping);
        $group->set($mapping['target_field_name'], $value);
      }

      // Handle the collection's newsletter field.
      if ($property === 'http://joinup.eu/collection/field_newsletter/enabled') {
        $value = [
          'enabled' => $values['http://joinup.eu/collection/field_newsletter/enabled'],
          'universe' => $values['http://joinup.eu/collection/field_newsletter/universe'],
          'service_id' => $values['http://joinup.eu/collection/field_newsletter/service_id'],
        ];
        $group->set('newsletter', $value);
      }
    }
  }

  /**
   * Saves the entity and preserve the path alias.
   *
   * @param \Drupal\joinup_rdf\Entity\RdfSyncEntityInterface $entity
   *   The entity to save.
   */
  protected function save(RdfSyncEntityInterface $entity): void {
    $pathautoOldPath = '/rdf_entity/' . strtr($entity->getUri(), self::URL_ENCODE_MAP);
    $alias = $this->pathAliasRepository->lookupBySystemPath($pathautoOldPath, 'en');
    if ($alias) {
      $entity->get('path')->pathauto = PathautoState::SKIP;
    }

    $entity->skip_notification = TRUE;
    $entity->skip_menu_creation = TRUE;
    $entity->save();

    if ($alias) {
      $internalUrl = '/' . $entity->toUrl()->getInternalPath();
      $this->connection->update('path_alias')
        ->fields(['path' => $internalUrl])
        ->condition('id', $alias['id'])
        ->execute();

      $this->connection->update('path_alias_revision')
        ->fields(['path' => $internalUrl])
        ->condition('id', $alias['id'])
        ->execute();
    }
  }

  /**
   * Parses the values from the flatten value array.
   *
   * @param array $values
   *   The values to parse.
   * @param array $mappingData
   *   The mapping data.
   *
   * @return array
   *   The parsed values.
   */
  protected function parseValue(array $values, array $mappingData): mixed {
    $parsed = [];
    $format = $mappingData['format'];

    foreach ($values as $value) {
      $parsedValue = match ($format) {
        'string' => ['value' => !empty($mappingData['serialized']) ? unserialize($value, ['allowed_classes' => FALSE]) : $value],
        'uri' => ['uri' => $value],
        'datetime' => ['value' => $value->getTimestamp()],
        'reference' => ['target_id' => $value],
        default => throw new \Exception('Unknown format: ' . $format),
      };

      if (isset($mappingData['mapped_term'])) {
        if ($term = MappedTerm::loadByUri($value)) {
          $parsedValue = [
            'target_id' => $term->id(),
          ];
        }
      }

      if (isset($mappingData['default_values'])) {
        $parsedValue = [
          'value' => $value,
          'format' => $mappingData['default_values']['format'],
        ];
      }

      if (isset($mappingData['trim'])) {
        $parsedValue['value'] = strlen($parsedValue['value']) > 255 ? substr($parsedValue['value'], 0, 255) : $parsedValue['value'];
      }

      $parsed[] = $parsedValue;
    }

    return $parsed;
  }

  /**
   * Retrieve the RDF data and flatten RDF values.
   *
   * @param string $uri
   *   The URI of the entity.
   * @param string $graph
   *   The graph to query.
   *
   * @return array
   *   The flattened RDF values in the form of an associative array. The keys
   *   are the URIs of the properties and the values are the values of the
   *   properties.
   */
  protected function getEntityData(string $uri, string $graph): array {
    $query = <<<SPARQL
      SELECT *
      FROM <$graph>
      WHERE {
        <$uri> ?property ?value .
      }
      SPARQL;
    $results = $this->sparqlEndpoint->query($query)->getArrayCopy();
    return $this->flattenValues($results);
  }

  /**
   * Flatten the RDF values.
   *
   * @param \EasyRdf\Sparql\Result[] $results
   *   The results to flatten.
   *
   * @return array
   *   The flattened values.
   */
  protected function flattenValues(array $results): array {
    $values = [];
    foreach ($results as $row) {
      $property = $row->property->getUri();
      $value = method_exists($row->value, 'getUri') ? $row->value->getUri() : $row->value->getValue();
      $values[$property][] = $value;
    }
    return $values;
  }

  /**
   * Asserts that the entity has been migrated properly.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to assert.
   * @param array $values
   *   The values to assert.
   */
  protected function assertMigratedEntity(EntityInterface $entity, array $values): void {
    // Reload the entity to get the values from the database.
    $loaded = $this->entityTypeManager->getStorage($entity->getEntityTypeId())->load($entity->id());
    foreach ($values as $uri => $value) {
      $fieldData = self::FIELD_MAPPING[$loaded->bundle()][$uri] ?? NULL;
      if (!$fieldData) {
        print sprintf('The field %s is not found in the mapping.', $uri);
        continue;
      }

      $targetFieldName = $fieldData['target_field_name'];
      $field = $loaded->get($targetFieldName);
      $value = $this->parseValue($value, $fieldData);
      if ($value !== $field->value) {
        print sprintf('The value of the field %s is not correct. Expected: %s, got: %s', $targetFieldName, print_r($value, TRUE), print_r($field->value, TRUE));
      }
    }
  }

  /**
   * Builds the expression that decodes a URI.
   *
   * @param string $baseExpression
   *   The base expression.
   *
   * @return string
   *   An MySQL expression that decodes an encoded URI.
   */
  public static function sqlExpressionUriDecode(string $baseExpression): string {
    return sprintf(self::URL_DECODE_SQL_EXPRESSION, $baseExpression);
  }

  /**
   * Builds the expression that encodes a URI.
   *
   * @param string $baseExpression
   *   The base expression.
   *
   * @return string
   *   An MySQL expression that encodes an encoded URI.
   */
  public static function sqlExpressionUriEncode(string $baseExpression): string {
    return sprintf(self::URL_ENCODE_SQL_EXPRESSION, $baseExpression);
  }

}
