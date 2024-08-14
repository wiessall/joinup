<?php

declare(strict_types=1);

namespace Drupal\joinup_rdf;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\joinup_rdf\Entity\RdfSyncEntityInterface;
use Drupal\joinup_solution\Entity\SolutionInterface;
use Drupal\joinup_taxonomy\Entity\MappedTerm;
use Drupal\og\OgGroupAudienceHelperInterface;
use Drupal\path_alias\AliasRepositoryInterface;
use Drupal\pathauto\PathautoState;
use Drupal\rdf_sync\RdfSyncMapper;
use Drupal\sparql_entity_storage\Driver\Database\sparql\ConnectionInterface;
use Drupal\sparql_entity_storage\UriEncoder;

/**
 * Helper class for the RDF2Node migration.
 */
final class MigrationHelper {

  /**
   * Mapping of fields from the RDF to the node for collections.
   */
  const array COLLECTION_FIELD_MAPPING = [
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
  ];

  const array SOLUTION_FIELD_MAPPING = [
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
  ];

  /**
   * Constructs a MigrationHelper object.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AliasRepositoryInterface $pathAliasRepository,
    protected Connection $connection,
    protected ConnectionInterface $sparqlEndpoint,
    protected RdfSyncMapper $rdfSyncMapper,
  ) {}

  /**
   * Migrate collection.
   *
   * @param string $uri
   *   The URI of the collection.
   */
  public function migrateCollection(string $uri): void {
    if ($entity = $this->rdfSyncMapper->getEntityByUri($uri)) {
      $entity->delete();
    }

    /** @var \Drupal\collection\Entity\CollectionInterface $entity */
    $entity = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'collection',
    ]);

    $entity->set('uri', $uri);
    $values = $this->getEntityDataAsFlatArray($uri, 'http://joinup.eu/collection/published');
    if (!empty($values)) {
      $this->massageCollectionValues($entity, $values);
      $this->saveEntityWithPathPreservation($entity, $uri);
      $this->assertMigratedEntity($entity, $values);
    }


    // Handle the draft content. If the entity has not been saved, it is an
    // unpublished entity, otherwise, it is a new revision.
    $values = $this->getEntityDataAsFlatArray($uri, 'http://joinup.eu/collection/draft');
    if (!empty($values)) {
      if (!$entity->isNew()) {
        $entity->setRevisionCreationTime($values['http://purl.org/dc/terms/modified'][0]->getTimestamp());
        $entity->setRevisionUserId($values['http://joinup.eu/owner/uid'][0]);
        $entity->setRevisionTranslationAffected(TRUE);
        $entity->setNewRevision();
      }

      $this->massageCollectionValues($entity, $values);
      $entity->skip_notification = TRUE;
      $entity->save();
      $this->assertMigratedEntity($entity, $values);
    }

    $this->updateReferenceValues($entity);
    $this->updateMenuLinks($entity);
  }

  /**
   * Migrate solution.
   *
   * @param string $uri
   *   The URI of the solution.
   */
  public function migrateSolution(string $uri): void {
    // Because we have migrated solution stubs in the release migration, we need
    // to check if the entity exists already first.
    $entity = $this->rdfSyncMapper->getEntityByUri($uri);
    if (!$entity) {
      $entity = $this->entityTypeManager->getStorage('node')->create([
        'type' => 'solution',
      ]);
    }

    $entity->set('uri', $uri);
    $values = $this->getEntityDataAsFlatArray($uri, 'http://joinup.eu/solution/published');
    if (!empty($values)) {
      $this->massageSolutionValues($entity, $values);
      $this->saveEntityWithPathPreservation($entity, $uri);
      $this->assertMigratedEntity($entity, $values);
    }

    // Handle the draft content. If the entity has not been saved, it is an
    // unpublished entity, otherwise, it is a new revision.
    $values = $this->getEntityDataAsFlatArray($uri, 'http://joinup.eu/solution/draft');
    if (!empty($values)) {
      if (!$entity->isNew()) {
        $entity->setRevisionCreationTime($values['http://purl.org/dc/terms/modified'][0]->getTimestamp());
        $entity->setRevisionUserId($values['http://joinup.eu/owner/uid'][0]);
        $entity->setRevisionTranslationAffected(TRUE);
        $entity->setNewRevision();
      }

      $this->massageSolutionValues($entity, $values);
      $entity->skip_notification = TRUE;
      $entity->save();
      $this->assertMigratedEntity($entity, $values);
    }

    $this->updateReferenceValues($entity);
    $this->updateMenuLinks($entity);
  }

  /**
   * Set the collection fields.
   *
   * @param \Drupal\collection\Entity\CollectionInterface $entity
   *   The entity to set the fields.
   * @param array $values
   *   The values to set.
   */
  protected function massageCollectionValues(CollectionInterface $entity, array $values): void {
    foreach (self::COLLECTION_FIELD_MAPPING as $property => $mapping) {
      $value = $values[$property] ?? NULL;
      if ($value) {
        $value = $this->parseValue($value, self::COLLECTION_FIELD_MAPPING[$property]);
        $entity->set($mapping['target_field_name'], $value);
      }

      // Handle the newsletter field.
      if ($property === 'http://joinup.eu/collection/field_newsletter/enabled') {
        $value = [
          'enabled' => $values['http://joinup.eu/collection/field_newsletter/enabled'],
          'universe' => $values['http://joinup.eu/collection/field_newsletter/universe'],
          'service_id' => $values['http://joinup.eu/collection/field_newsletter/service_id'],
        ];
        $entity->set('newsletter', $value);
      }
    }
  }

  /**
   * Set the solution fields.
   *
   * @param \Drupal\joinup_solution\Entity\SolutionInterface $entity
   *   The entity to set the fields.
   * @param array $values
   *   The values to set.
   */
  protected function massageSolutionValues(SolutionInterface $entity, array $values): void {
    foreach (self::SOLUTION_FIELD_MAPPING as $property => $mapping) {
      $value = $values[$property] ?? NULL;
      if ($value) {
        $value = $this->parseValue($value, self::SOLUTION_FIELD_MAPPING[$property]);
        $entity->set($mapping['target_field_name'], $value);
      }
    }

    // Set the collection. We need to get the collection URI from SPARQL, load
    // the collection by mapped URI from node storage, and pass it to the
    // solution.
    $query = <<<SPARQL
      SELECT ?s
      FROM <http://joinup.eu/collection/published>
      FROM <http://joinup.eu/collection/draft>
      WHERE {
        ?s <http://www.w3.org/ns/dcat#dataset> <{$entity->getUri()}> .
      }
      SPARQL;

    $results = $this->sparqlEndpoint->query($query)->getArrayCopy();
    if ($results) {
      $collectionUri = $results[0]->s->getUri();
      $collection = $this->rdfSyncMapper->getEntityByUri($collectionUri);
      if ($collection) {
        $entity->set(OgGroupAudienceHelperInterface::DEFAULT_FIELD, $collection->id());
      }
      else {
        throw new \Exception('Collection not found for solution: ' . $entity->getUri());
      }
    }
  }

  /**
   * Saves the entity and preserve the path alias.
   *
   * @param \Drupal\joinup_rdf\Entity\RdfSyncEntityInterface $entity
   *   The entity to save.
   * @param string $uri
   *   The URI of the entity.
   */
  protected function saveEntityWithPathPreservation(RdfSyncEntityInterface $entity, string $uri): void {
    $pathautoOldPath = '/rdf_entity/' . UriEncoder::encodeUrl($entity->getUri());
    $has_alias = $this->pathAliasRepository->lookupBySystemPath($pathautoOldPath, 'en');
    if ($has_alias) {
      $entity->get('path')->pathauto = PathautoState::SKIP;
    }

    $entity->skip_notification = TRUE;
    $entity->skip_menu_creation = TRUE;
    $entity->save();

    if ($has_alias) {
      $internalUrl = '/' . $entity->toUrl()->getInternalPath();
      $this->connection->update('path_alias')
        ->fields(['path' => $internalUrl])
        ->condition('path', $pathautoOldPath)
        ->execute();

      $this->connection->update('path_alias_revision')
        ->fields(['path' => $internalUrl])
        ->condition('path', $pathautoOldPath)
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
        'string' => ['value' => $value],
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
  protected function getEntityDataAsFlatArray(string $uri, string $graph): array {
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
   * Update the reference values.
   *
   * @param \Drupal\joinup_rdf\Entity\RdfSyncEntityInterface $entity
   *   The entity to update the reference values.
   */
  protected function updateReferenceValues(RdfSyncEntityInterface $entity): void {
    $tableData = [
      'file_usage' => 'id',
      'flagging' => 'entity_id',
      'invitation' => 'entity_id',
      'meta_entity' => 'target__target_id',
      'joinup_group_node_access' => 'entity_id',
      'og_membership' => 'entity_id',
      'node__og_audience' => 'og_audience_target_id',
      'node_revision__og_audience' => 'og_audience_target_id',
      'node__field_shared_in' => 'field_shared_in_target_id',
      'node_revision__field_shared_in' => 'field_shared_in_target_id',
      'media__og_audience' => 'og_audience_target_id',
      'media_revision__og_audience' => 'og_audience_target_id',
      'ogmenu_instance__og_audience' => 'og_audience_target_id',
    ];

    foreach ($tableData as $table => $targetField) {
      if ($table === 'file_usage') {
        // Because we have created the entity and passed the file to it, there
        // is already a file usage entry for the file. We need to simply clean
        // up the old file usage entry.
        $this->connection->delete($table)
          ->condition('type', 'rdf_entity')
          ->condition('id', $entity->getUri())
          ->execute();
      }
      $this->connection->update($table)
        ->fields([$targetField => $entity->id()])
        ->condition($targetField, $entity->getUri())
        ->execute();
    }
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
    return;
    // Reload the entity to get the values from the database.
    $loaded = $this->entityTypeManager->getStorage($entity->getEntityTypeId())->load($entity->id());
    foreach ($values as $uri => $value) {
      $fieldData = $loaded instanceof CollectionInterface ? self::COLLECTION_FIELD_MAPPING[$uri] : self::SOLUTION_FIELD_MAPPING[$uri];
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
   * Updates the menu link items related to RDF entities.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to update the menu links.
   */
  protected function updateMenuLinks(EntityInterface $entity): void {
    $tables = [
      'menu_link_content_data',
      'menu_link_content_field_revision',
    ];

    // Replace rdf_entity with node and encoded ID with entity ID.
    $encodedUri = UriEncoder::encodeUrl($entity->getUri());
    foreach ($tables as $table) {
      $this->connection->update($table)
        ->fields([
          'link__uri' => 'entity:node/' . $entity->id(),
          'link__options' => serialize([]),
        ])
        ->condition('link__uri', sprintf('entity:rdf_entity/%s', $encodedUri))
        ->execute();

      $this->connection->update($table)
        ->fields([
          'link__uri' => sprintf('route:entity.node.member_overview;node=%s', $entity->id()),
          'link__options' => serialize([]),
        ])
        ->condition('link__uri', sprintf('entity.rdf_entity.member_overview;rdf_entity=%s', $encodedUri))
        ->execute();

      $this->connection->update($table)
        ->fields([
          'link__uri' => sprintf('route:entity.node.glossary;node=%s', $entity->id()),
          'link__options' => serialize([]),
        ])
        ->condition('link__uri', sprintf('entity.rdf_entity.glossary;rdf_entity=%s', $encodedUri))
        ->execute();

      $this->connection->update($table)
        ->fields([
          'link__uri' => sprintf('route:view.discussions.overview;node=%s', $entity->id()),
          'link__options' => serialize([]),
        ])
        ->condition('link__uri', sprintf('view.discussions.overview;rdf_entity=%s', $encodedUri))
        ->execute();
    }

    $this->connection->update('menu_link_content_data')
      ->fields([
        'link__uri' => sprintf('route:joinup_menu.website_link;node=%s', $entity->id()),
        'link__options' => serialize([]),
      ])
      ->condition('link__uri', sprintf('joinup_menu.website_link;rdf_entity=%s', $encodedUri))
      ->execute();

    $this->connection->update('menu_link_content_data')
      ->fields([
        'link__uri' => sprintf('route:asset_release.solution_asset_release.overview;node=%s', $entity->id()),
        'link__options' => serialize([]),
      ])
      ->condition('link__uri', sprintf('asset_release.solution_asset_release.overview;rdf_entity=%s', $encodedUri))
      ->execute();

    $this->connection->update('menu_link_content_data')
      ->fields([
        'link__uri' => sprintf('route:joinup_menu.documentation_file;node=%s', $entity->id()),
        'link__options' => serialize([]),
      ])
      ->condition('link__uri', sprintf('joinup_menu.documentation_file;rdf_entity=%s', $encodedUri))
      ->execute();

    $this->connection->update('menu_link_content_data')
      ->fields([
        'link__uri' => sprintf('route:entity.node.eif_perspective;node=%s', $entity->id()),
        'link__options' => serialize([]),
      ])
      ->condition('link__uri', sprintf('entity.rdf_entity.eif_perspective;rdf_entity=%s', $encodedUri))
      ->execute();
  }

}
