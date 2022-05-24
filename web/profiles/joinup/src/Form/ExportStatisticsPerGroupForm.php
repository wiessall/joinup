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
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The MySQL database connection.
   *
   * @var \Drupal\Core\Database\Database
   */
  protected $mysql;

  /**
   * The SPARQL database connection.
   *
   * @var \Drupal\sparql_entity_storage\Driver\Database\sparql\ConnectionInterface
   */
  protected $sparql;

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
  public function __construct(CacheBackendInterface $cache, DateFormatterInterface $date_formatter, TimeInterface $time, EntityTypeManagerInterface $entityTypeManager, Connection $mysql, ConnectionInterface $sparql) {
    parent::__construct($cache, $date_formatter, $time);
    $this->entityTypeManager = $entityTypeManager;
    $this->mysql = $mysql;
    $this->sparql = $sparql;
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
  public function getFormId() {
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
    $group = $rdf_storage->load((string) $entity_id);

    // Fetch the nodes that belong to the group.
    $group_content_data = $this->mysql->query($this->getGroupContentQuery($entity_id))->fetchAll();
    $group_content = [
      'custom_page' => 0,
      'discussion' => 0,
      'document' => 0,
      'event' => 0,
      'news' => 0,
      'solution' => 0,
      'asset_release' => 0,
      'asset_distribution' => 0,
    ];

    $group_content_keys = array_keys($group_content);
    foreach ($group_content_data as $data) {
      if (in_array($data->type, $group_content_keys)) {
        $group_content[$data->type]++;
      }
    }

    // For solutions, count releases and distributions.
    if ($group instanceof SolutionInterface) {
      $releases_and_distributions_ids = $this->sparql->query($this->getRelasesAndDistributionsQuery($entity_id));

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
      'Custom pages' => $group_content['custom_page'],
      'Discussions' => $group_content['discussion'],
      'Documents' => $group_content['document'],
      'Events' => $group_content['event'],
      'News' => $group_content['news'],
      'Solutions' => $group_content['solution'],
      'Releases' => $group_content['asset_release'],
      'Distributions' => $group_content['asset_distribution'],
      'Total number of items' => $group_content['custom_page']
      + $group_content['discussion']
      + $group_content['document']
      + $group_content['event']
      + $group_content['news']
      + $group_content['asset_release']
      + $group_content['asset_distribution'],
      'Number of members' => $memberships,
      'Description word count' => str_word_count(strip_tags($description)),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {}

  /**
   * Helper method to return a query to fetch group content.
   *
   * @param string $entity_id
   *   The group entity ID.
   *
   * @return string
   *   The query.
   */
  private function getGroupContentQuery(string $entity_id): string {
    return <<<QUERY
SELECT og.entity_id, n.type
FROM {node__og_audience} og
LEFT JOIN {node_field_data} n ON og.entity_id = n.nid
WHERE og.og_audience_target_id = '{$entity_id}'
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
  private function getRelasesAndDistributionsQuery(string $entity_id): string {
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
