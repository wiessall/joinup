<?php

declare(strict_types=1);

namespace Drupal\joinup\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\joinup\ExportBatchedDataFormBase;
use Drupal\joinup_solution\Entity\SolutionInterface;
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
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
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
    protected CacheBackendInterface $cache,
    protected DateFormatterInterface $dateFormatter,
    protected TimeInterface $time,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected Connection $mysql,
    protected ConnectionInterface $sparql,
  ) {
    parent::__construct($cache, $dateFormatter, $time);
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
SELECT * WHERE
{
    {
        SELECT DISTINCT ?entity_id
        FROM <http://joinup.eu/solution/published>
        WHERE {
            ?entity_id <http://joinup.eu/is/workflow_status> ?o .
            FILTER (!regex(?o, "archived", "i"))
        }
    }
    UNION
    {
        SELECT DISTINCT ?entity_id
        FROM <http://joinup.eu/collection/published>
        WHERE {
            ?entity_id a ?bundle_uri .
        }
    }
}
QUERY;

    // Initialize almost everything (some counters are not initialized here).
    $query_results = $this->sparql->query($ids_query);

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
    $rdf_storage = $this->entityTypeManager->getStorage('node');
    /** @var \Drupal\collection\Entity\CollectionInterface|\Drupal\joinup_solution\Entity\SolutionInterface $group */
    $group = $rdf_storage->load((string) $entity_id);
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
      'distribution' => 0,
      // RDF entities.
      'solution' => 0,
      'release' => 0,
    ];

    foreach ($group_content_data as $data) {
      if (!isset($group_content[$data->type])) {
        continue;
      }

      if (is_int($group_content[$data->type])) {
        $group_content[$data->type] += $data->entities;
        continue;
      }

      if (is_array($group_content[$data->type]) && isset($group_content[$data->type][$data->state])) {
        $group_content[$data->type][$data->state] += $data->entities;
      }
    }

    // Get the group content ids.
    $content = $group->getGroupContentIds();
    $content_ids = [];

    array_walk_recursive($content, function ($value) use (&$content_ids): void {
      $content_ids[] = $value;
    });

    // For solutions, count releases and distributions.
    if ($group instanceof SolutionInterface) {
      foreach ($group->releases_and_distributions->referencedEntities() as $entity) {
        $group_content[$entity->bundle()]++;
      }
      $content_ids[] = $group->id();
    }
    // For collections simply pass the number of solutions (affiliates).
    else {
      $group_content['solution'] = $group->field_ar_affiliates->count();
    }

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
      'Releases' => $group_content['release'],
      'Distributions' => $group_content['distribution'],
      'Total number of items' =>
      $group_content['custom_page']['active']
      + $group_content['discussion']['active']
      + $group_content['document']['active']
      + $group_content['event']['active']
      + $group_content['news']['active']
      + $group_content['release']
      + $group_content['distribution'],
      'Total number of archived items' =>
      $group_content['custom_page']['archived']
      + $group_content['discussion']['archived']
      + $group_content['document']['archived']
      + $group_content['event']['archived']
      + $group_content['news']['archived'],
      'Number of members' => collection_member_count($group),
      'Description word count' => str_word_count(strip_tags($description)),
      'Total number of likes' => !empty($content_ids) ? $this->getLikesCount($content_ids) : 0,
    ];
  }

  /**
   * Retrieves the sum of likes for the provided group entity IDs.
   *
   * @param array $entity_ids
   *   An array of group entity IDs for calculating the sum of likes.
   *
   * @return string
   *   The sum of likes as a string value.
   */
  private function getLikesCount(array $entity_ids): string {
    $query = $this->mysql->select('flag_counts', 'fc');
    $query->addExpression('IFNULL(SUM(count), 0)', 'sum');
    $query->condition('flag_id', 'like_node');
    $query->condition('entity_id', $entity_ids, 'IN');
    return $query->execute()->fetchField();
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

}
