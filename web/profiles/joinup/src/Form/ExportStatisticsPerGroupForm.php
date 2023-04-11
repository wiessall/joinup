<?php

declare(strict_types = 1);

namespace Drupal\joinup\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\joinup\ExportBatchedDataFormBase;
use Drupal\solution\Entity\SolutionInterface;
use Drupal\sparql_entity_storage\Driver\Database\sparql\ConnectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to export the list of group facilitators and administrators.
 */
class ExportStatisticsPerGroupForm extends ExportBatchedDataFormBase {

  /**
   * Constructs an ExportUserListForm.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The default cache bin.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $mysql
   *   The MySQL connection instance.
   * @param \Drupal\sparql_entity_storage\Driver\Database\sparql\ConnectionInterface $sparql
   *   The SPARQL connection instance.
   */
  public function __construct(
    CacheBackendInterface $cache,
    DateFormatterInterface $date_formatter,
    TimeInterface $time,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected Connection $mysql,
    protected ConnectionInterface $sparql
  ) {
    parent::__construct($cache, $date_formatter, $time);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('cache.default'),
      $container->get('date.formatter'),
      $container->get('datetime.time'),
      $container->get('entity_type.manager'),
      $container->get('database'),
      $container->get('sparql.endpoint'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getCacheTagName(): string {
    return 'group_administration_list';
  }

  /**
   * {@inheritdoc}
   */
  protected function getRedirectRouteName(): string {
    return 'joinup.statistics_per_group';
  }

  /**
   * {@inheritdoc}
   */
  protected function getDownloadedFileName(): string {
    return 'statistics_export.csv';
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'joinup_export_statistics_per_group';
  }

  /**
   * Generated the user list data in a batch process.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  public function generateData(array &$form, FormStateInterface $form_state): void {
    // Query to fetch all groups.
    $ids_query = <<<QUERY
SELECT DISTINCT ?entity_id
FROM <http://joinup.eu/collection/published>
FROM <http://joinup.eu/solution/published>
WHERE {
  ?entity_id a ?bundle_uri .
}
QUERY;

    // Initialize almost everything (some counters are not initialized here).
    $sparql_connection = \Drupal::getContainer()->get('sparql.endpoint');
    $query_results = $sparql_connection->query($ids_query);

    $batch_operations = [];
    foreach ($query_results->getArrayCopy() as $query_result) {
      $batch_operations[] = [
        [$this, 'generateGroupStatistics'],
        [$query_result->entity_id],
      ];
    }

    if ($batch_operations) {
      $batch = [
        'title' => 'Exporting statistics per group',
        'init_message' => 'Starting collection of data.',
        'operations' => $batch_operations,
        'finished' => [$this, 'storeToCache'],
      ];
      batch_set($batch);
    }
  }

  /**
   * Generates statistics for a group.
   *
   * @param string $entity_id
   *   The group ID.
   * @param array $context
   *   The batch context.
   */
  public function generateGroupStatistics(string $entity_id, array &$context): void {
    $rdf_storage = $this->entityTypeManager->getStorage('rdf_entity');
    /** @var \Drupal\collection\Entity\CollectionInterface|\Drupal\solution\Entity\SolutionInterface $group */
    $group = $rdf_storage->load($entity_id);

    // Fetch the nodes that belong to the group.
    $group_content_data = $this->mysql->query(
      $this->getGroupContentQuery(), [':parent_entity_id' => $entity_id]
    )->fetchAll();
    $group_content = [
      // Node entities.
      'custom_page' => ['active' => 0, 'archived' => 0],
      'discussion' => ['active' => 0, 'archived' => 0],
      'document' => ['active' => 0, 'archived' => 0],
      'event' => ['active' => 0, 'archived' => 0],
      'news' => ['active' => 0, 'archived' => 0],
      // RDF entities.
      'solution' => 0,
      'asset_release' => 0,
      'asset_distribution' => 0,
    ];

    $group_content_keys = array_keys($group_content);
    foreach ($group_content_data as $data) {
      if (!in_array($data->type, $group_content_keys)) {
        continue;
      }

      if ($data->state === 'archived') {
        $group_content[$data->type]['archived'] += $data->entities;
      }
      else {
        $group_content[$data->type]['active'] += $data->entities;
      }
    }

    // For solutions, count releases and distributions.
    if ($group instanceof SolutionInterface) {
      $releases_and_distributions_ids = $this->sparql->query($this->getReleasesAndDistributionsQuery($entity_id));

      /** @var \EasyRdf\Sparql\Result $result */
      foreach ($releases_and_distributions_ids as $result_id) {
        $entity = $rdf_storage->load((string) $result_id->entity_id);
        $group_content[$entity->bundle()]++;
      }
    }
    // For collections simply pass the number of solutions (affiliates).
    else {
      $group_content['solution'] = $group->field_ar_affiliates->count();
    }

    // Get the total members count.
    $memberships = $this->mysql->query($this->getMembersCountQuery($group->id()))->fetchField();
    $description = $group->hasField('field_ar_description') ? $group->field_ar_description->value : $group->field_is_description->value;

    // Add data to the final array.
    $context['results']['data'][$group->id()] = [
      'Entity ID' => $group->id(),
      'Label' => $group->label(),
      'Bundle' => ucfirst($group->bundle()),
      'Last update date' => \Drupal::getContainer()
        ->get('date.formatter')
        ->format($group->getChangedTime(), 'c'),
      'Custom pages' => $group_content['custom_page']['active'],
      'Discussions' => $group_content['discussion']['active'],
      'Documents' => $group_content['document']['active'],
      'Events' => $group_content['event']['active'],
      'News' => $group_content['news']['active'],
      'Solutions' => $group_content['solution'],
      'Releases' => $group_content['asset_release'],
      'Distributions' => $group_content['asset_distribution'],
      'Total number of items' =>
      $group_content['custom_page']['active']
      + $group_content['discussion']['active']
      + $group_content['document']['active']
      + $group_content['event']['active']
      + $group_content['news']['active']
      + $group_content['asset_release']
      + $group_content['asset_distribution'],
      'Total number of archived items' =>
      $group_content['custom_page']['archived']
      + $group_content['discussion']['archived']
      + $group_content['document']['archived']
      + $group_content['event']['archived']
      + $group_content['news']['archived'],
      'Number of members' => $memberships,
      'Description word count' => str_word_count(strip_tags((string) $description)),
    ];
  }

  /**
   * Helper method to return a query to fetch group content.
   *
   * @return string
   *   The query.
   */
  private function getGroupContentQuery(): string {
    return <<<QUERY
SELECT count(og.entity_id) as entities, n.type, s.field_state_value as state
FROM {node__og_audience} og
LEFT JOIN {node_field_data} n ON og.entity_id = n.nid
LEFT JOIN {node__field_state} s ON n.nid = s.entity_id AND n.vid = s.revision_id
WHERE og.og_audience_target_id = :parent_entity_id
group by n.type, s.field_state_value
QUERY;
  }

  /**
   * Helper method to return a query to fetch releases and distribution IDs.
   *
   * @param string $entity_id
   *   The group ID.
   *
   * @return string
   *   The query.
   */
  private function getReleasesAndDistributionsQuery(string $entity_id): string {
    return <<<QUERY
SELECT DISTINCT ?entity_id
FROM <http://joinup.eu/asset_release/published>
FROM <http://joinup.eu/asset_distribution/published>
WHERE {
  {
    ?entity_id <http://purl.org/dc/terms/isVersionOf> <{$entity_id}>
  }
  UNION
  {
    ?entity_id <http://joinup.eu/rdf_entity/group> <{$entity_id}>
  }
}
QUERY;
  }

  /**
   * Helper method to return a query to fetch the number of members in a group.
   *
   * @param string $entity_id
   *   The group ID.
   *
   * @return string
   *   The query.
   */
  private function getMembersCountQuery(string $entity_id): string {
    return <<<QUERY
SELECT COUNT(og.uid) FROM {og_membership} og
WHERE entity_id = '{$entity_id}' AND state = 'active'
QUERY;
  }

}
