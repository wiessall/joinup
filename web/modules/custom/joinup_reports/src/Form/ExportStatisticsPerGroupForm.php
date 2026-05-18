<?php

declare(strict_types=1);

namespace Drupal\joinup_reports\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\joinup_group\JoinupGroupHelper;
use Drupal\joinup_reports\ExportBatchedDataFormBase;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Provides a form to export the list of group facilitators and administrators.
 */
class ExportStatisticsPerGroupForm extends ExportBatchedDataFormBase {

  // Use the serialization trait to ensure the read only params get instantiated
  // given the fact that their read only status prevents the parent class from
  // doing so.
  use DependencySerializationTrait;

  public function __construct(
    #[Autowire(service: 'cache.default')]
    protected readonly CacheBackendInterface $cache,
    protected DateFormatterInterface $dateFormatter,
    protected TimeInterface $time,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'database')]
    protected readonly Connection $mysql,
  ) {
    parent::__construct($cache, $dateFormatter, $time);
  }

  /**
   * {@inheritdoc}
   */
  protected function getRedirectRouteName(): string {
    return 'joinup_reports.statistics_per_group';
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
    $storage = $this->entityTypeManager->getStorage('node');
    $query = $storage->getQuery()->accessCheck(FALSE)
      ->condition('status', NodeInterface::PUBLISHED)
      ->condition('field_state', 'archived', '<>')
      ->condition('type', array_values(JoinupGroupHelper::GROUP_BUNDLES), 'IN');

    $batch_operations = [];
    foreach ($query->execute() as $nid) {
      $batch_operations[] = [
        [$this, 'generateGroupStatistics'],
        [$nid],
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
    $storage = $this->entityTypeManager->getStorage('node');
    /** @var \Drupal\collection\Entity\CollectionInterface|\Drupal\joinup_solution\Entity\SolutionInterface $group */
    $group = $storage->load($entity_id);

    // Fetch the nodes that belong to the group.
    $group_content_data = $this->mysql->query(
      $this->getGroupContentQuery(), [':parent_entity_id' => $entity_id]
    )->fetchAll();
    $group_content = [
      'custom_page' => ['published' => 0, 'archived' => 0],
      'discussion' => ['published' => 0, 'archived' => 0],
      'document' => ['published' => 0, 'archived' => 0],
      'event' => ['published' => 0, 'archived' => 0],
      'news' => ['published' => 0, 'archived' => 0],
      'distribution' => ['published' => 0, 'archived' => 0],
      'solution' => ['published' => 0, 'archived' => 0],
      'release' => ['published' => 0, 'archived' => 0],
    ];

    foreach ($group_content_data as $data) {
      if (!isset($group_content[$data->type])) {
        continue;
      }

      if (is_array($group_content[$data->type]) && isset($group_content[$data->type][$data->state])) {
        $group_content[$data->type][$data->state] += $data->entities;
      }
    }

    // Get the group content ids.
    $content = $group->getGroupContentIds();
    $content_ids = [];
    $content_ids[] = $group->id();

    array_walk_recursive($content, function ($value) use (&$content_ids): void {
      $content_ids[] = $value;
    });

    $description_field = $group->get($group->getDescriptionField());
    $description_word_count = !$description_field->isEmpty() ? str_word_count(strip_tags($description_field->getString())) : 0;

    $total_published = 0;
    $total_archived = 0;
    foreach ($group_content as $content_data) {
      $total_published += $content_data['published'];
      $total_archived += $content_data['archived'];
    }

    // Add data to the final array.
    $context['results']['data'][$group->id()] = [
      'Entity ID' => $group->id(),
      'Label' => $group->label(),
      'Bundle' => ucfirst($group->bundle()),
      'Last update date' => $this->dateFormatter->format($group->getChangedTime(), 'c'),
      'Custom pages' => $group_content['custom_page']['published'],
      'Discussions' => $group_content['discussion']['published'],
      'Documents' => $group_content['document']['published'],
      'Events' => $group_content['event']['published'],
      'News' => $group_content['news']['published'],
      'Solutions' => $group_content['solution']['published'],
      'Releases' => $group_content['release']['published'],
      'Distributions' => $group_content['distribution']['published'],
      'Total number of items' => $total_published,
      'Total number of archived items' => $total_archived,
      'Number of members' => $group->getActiveMemberCount(),
      'Description word count' => $description_word_count,
      'Total number of likes' => $this->getCountFlag($content_ids, 'like'),
      'Total number of bookmarks' => $this->getCountFlag($content_ids, 'bookmark'),
    ];
  }

  /**
   * Gets the flag count of selected type for the provided group entity IDs.
   *
   * @param array $entity_ids
   *   An array of group entity IDs for calculating the flag count.
   * @param string $type
   *   Flag type.
   *
   * @return string
   *   The flag count as a string value.
   */
  private function getCountFlag(array $entity_ids, string $type): string {
    $query = $this->mysql->select('flag_counts', 'fc');
    $query->addExpression('IFNULL(SUM(count), 0)', 'sum');
    $query->condition('flag_id', $type);
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
