<?php

declare(strict_types=1);

namespace Drupal\interoperable_europe\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\UseCacheBackendTrait;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\assessments\AssessmentInterface;
use Drupal\interoperable_europe\InteroperableEuropeAcademyInterface;
use Drupal\interoperable_europe\InteroperableEuropeCommunityInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Provides the IOPEU community monitoring block.
 */
#[Block(
  id: "interoperable_europe_monitoring",
  admin_label: new TranslatableMarkup("IOPEU Community Monitoring"),
  category: new TranslatableMarkup("Interoperable Europe"),
)]
class IopeuCommunityMonitoring extends BlockBase implements ContainerFactoryPluginInterface {

  use UseCacheBackendTrait;

  /**
   * Cache prefix to be used in the cache keys of the queries.
   *
   * @const string
   */
  private const string CACHE_PREFIX = 'iopeu-community-monitoring:';

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly Connection $database,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'cache.default')]
    CacheBackendInterface $cacheBackend,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->cacheBackend = $cacheBackend;
  }

  /**
   * {@inheritdoc}
   */
  public function build(?array &$cacheTagsUsed = []): array {
    $cacheTagsUsed = [];
    $rows = [];
    $build['table'] = ['#type' => 'table', '#rows' => &$rows];

    // Number of public and currently published solutions.
    $rows['non-archived-sol'] = [
      $this->t('Total number of non-archived interoperability Solutions on the Portal'),
      $this->getPublicSolutionCount('published', $cacheTagsUsed),
    ];
    // Number of public archived solutions.
    $rows['archived-sol'] = [
      $this->t('Total number of archived interoperability Solutions on the Portal'),
      $this->getPublicSolutionCount('archived', $cacheTagsUsed),
    ];
    // Number of Interoperable Europe Solutions.
    $rows['interop-sol'] = [
      $this->t('Number of Interoperable Europe Solutions'),
      $this->getInteroperableSolutionCount($cacheTagsUsed),
    ];
    // Portal Solutions that have been mentioned in assessment reports.
    $rows['sols-mentioned-in-report'] = [
      $this->t('Number of Portal Solutions that have been mentioned in an assessment'),
      $this->getPublicPortalReportSolutionsCount($cacheTagsUsed),
    ];
    // Number of published assessment reports.
    $rows['published-reports'] = [
      $this->t('Number of published interoperability assessment reports'),
      $this->getNumberOfPublishedReports($cacheTagsUsed),
    ];
    // Number of IOPEU Community members.
    $rows['active-iopeu-members'] = [
      $this->t('Number of IOPEU Community members'),
      $this->getActiveIopeuMemberCount($cacheTagsUsed),
    ];
    // Number of eLearning courses published on the Portal.
    $rows['elearning-courses'] = [
      $this->t('Number of eLearning courses published on the portal'),
      $this->getPublishedAcademySolutionCount($cacheTagsUsed),
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    $this->build($tags);
    return Cache::mergeTags(
      parent::getCacheTags(),
      $tags,
    );
  }

  /**
   * Returns the ID of the IOPEU Community collection node.
   *
   * @return string
   *   The ID of the IOPEU Community collection node.
   *
   * @throws \TypeError
   *   If the IOPEU Community collection doesn't exist.
   */
  protected function getCommunityCollectionId(): string {
    static $collectionId;

    if (!isset($collectionId)) {
      $collectionId = current(
        $this->entityTypeManager->getStorage('node')->getQuery()
          ->accessCheck(FALSE)
          ->condition('uuid', InteroperableEuropeCommunityInterface::GROUP_UUID)
          ->execute()
      );
    }

    return $collectionId;
  }

  /**
   * Returns the ID of the IOPEU Academy collection node.
   *
   * @return string
   *   The ID of the IOPEU Academy collection node.
   *
   * @throws \TypeError
   *   If the IOPEU Academy collection doesn't exist.
   */
  protected function getAcademyCollectionId(): string {
    static $academyId;

    if (!isset($academyId)) {
      $academyId = current(
        $this->entityTypeManager->getStorage('node')->getQuery()
          ->accessCheck(FALSE)
          ->condition('uuid', InteroperableEuropeAcademyInterface::GROUP_UUID)
          ->execute()
      );
    }

    return $academyId;
  }

  /**
   * Returns the ID of the assessment report asset collector collection.
   *
   * @return string
   *   The ID of the assessment report asset collector collection.
   *
   * @throws \TypeError
   *   If the collector collection doesn't exist.
   */
  protected function getAssessmentCollectorId(): string {
    static $collectorId;

    if (!isset($collectorId)) {
      $collectorId = current(
        $this->entityTypeManager->getStorage('node')->getQuery()
          ->accessCheck(FALSE)
          ->condition('uuid', AssessmentInterface::SOLUTION_COLLECTOR)
          ->execute()
      );
    }

    return $collectorId;
  }

  /**
   * Returns the number of the public collections with the specified state.
   *
   * @param string $workflowState
   *   The workflow state to count.
   * @param array $cacheTagsUsed
   *   Optional array to add the cache tags which affect the calculation. Passed
   *   by reference.
   *
   * @return int
   *   Number of the public collections with the specified state.
   */
  protected function getPublicSolutionCount(string $workflowState, array &$cacheTagsUsed = []): int {
    $relevantCacheTags = ['node_list:solution'];
    $cacheTagsUsed = Cache::mergeTags($cacheTagsUsed, $relevantCacheTags);
    $cid = static::CACHE_PREFIX . __FUNCTION__ . $workflowState;
    $cachedData = $this->cacheGet($cid);
    if ($cachedData) {
      return $cachedData->data;
    }

    $count = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'solution')
      ->condition('status', 1)
      ->condition('field_state', $workflowState)
      ->condition('og_audience', $this->getAssessmentCollectorId(), '<>')
      ->count()
      ->execute();
    $this->cacheSet(cid: $cid, data: $count, tags: $relevantCacheTags);
    return $count;
  }

  /**
   * Returns the number of the public collections having the interoperable flag.
   *
   * @param array $cacheTagsUsed
   *   Optional array to add the relevant cache tags to, passed by reference.
   *
   * @return int
   *   Number of the public collections having the interoperable flag.
   */
  protected function getInteroperableSolutionCount(array &$cacheTagsUsed = []): int {
    $relevantCacheTags = [
      'flagging_list:interoperability',
      'node_list:solution',
    ];
    $cacheTagsUsed = Cache::mergeTags($cacheTagsUsed, $relevantCacheTags);
    $cid = static::CACHE_PREFIX . __FUNCTION__;
    $cachedData = $this->cacheGet($cid);
    if ($cachedData) {
      return $cachedData->data;
    }

    $query = $this->database->select('flagging', 'f')
      ->condition('f.entity_type', 'node')
      ->condition('f.flag_id', 'interoperability');
    $query->innerJoin('node_field_data', 'nfd', 'nfd.nid = f.entity_id');
    $query->join('node__field_state', 's', 's.entity_id = nfd.nid');
    $query->condition('nfd.type', 'solution')
      ->condition('s.field_state_value', 'published')
      ->condition('nfd.status', 1);

    $count = (int) $query->countQuery()->execute()->fetchField();
    $this->cacheSet(cid: $cid, data: $count, tags: $relevantCacheTags);
    return $count;
  }

  /**
   * Returns the number of the public collections having the interoperable flag.
   *
   * @param array $cacheTagsUsed
   *   Optional array to add the cache tags which affect the calculation. Passed
   *   by reference.
   *
   * @return int
   *   Number of the public collections having the interoperable flag.
   */
  protected function getPublicPortalReportSolutionsCount(array &$cacheTagsUsed = []): int {
    $relevantCacheTags = [
      'paragraph_list:binding_requirement',
      'assessment_list:report',
      'node_list:solution',
    ];
    $cacheTagsUsed = Cache::mergeTags($cacheTagsUsed, $relevantCacheTags);
    $cid = static::CACHE_PREFIX . __FUNCTION__;
    $cachedData = $this->cacheGet($cid);
    if ($cachedData) {
      return $cachedData->data;
    }

    $query = $this->database->select('paragraph__asset', 'p')->distinct()
      ->fields('p', ['asset_target_id']);
    $query->join('paragraphs_item_field_data', 'pd', 'pd.id = p.entity_id');
    $query->join('assessment_field_data', 'ad', 'ad.id = pd.parent_id');
    $query->join('node_field_data', 'n', 'n.nid = p.asset_target_id');
    $query->join('node__field_state', 's', 's.entity_id = p.asset_target_id');
    $query->join('node__og_audience', 'a', 'a.entity_id = p.asset_target_id');

    $query->condition('n.type', 'solution')
      // Public solutions. Probably unnecessary, but better to be sure.
      ->condition('n.status', 1)
      // Count only published state.
      ->condition('s.field_state_value', 'published')
      // Exclude solutions connected to the assessment solution collector group.
      ->condition('a.og_audience_target_id', $this->getAssessmentCollectorId(), '<>')
      // Only published assessment repo.
      ->condition('ad.status', 1);

    $count = (int) $this->database->select($query)->countQuery()->execute()->fetchField();
    $this->cacheSet(cid: $cid, data: $count, tags: $relevantCacheTags);
    return $count;
  }

  /**
   * Returns the number of published assessment reports.
   *
   * @param array $cacheTagsUsed
   *   Optional array to add the cache tags which affect the calculation. Passed
   *   by reference.
   *
   * @return int
   *   The number of published assessment reports.
   */
  protected function getNumberOfPublishedReports(array &$cacheTagsUsed = []): int {
    $relevantCacheTags = ['assessment_list:report'];
    $cacheTagsUsed = Cache::mergeTags($cacheTagsUsed, $relevantCacheTags);
    $cid = static::CACHE_PREFIX . __FUNCTION__;
    $cachedData = $this->cacheGet($cid);
    if ($cachedData) {
      return $cachedData->data;
    }

    $count = (int) $this->entityTypeManager->getStorage('assessment')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'report')
      ->condition('status', 1)
      ->count()
      ->execute();
    $this->cacheSet(cid: $cid, data: $count, tags: $relevantCacheTags);
    return $count;
  }

  /**
   * Returns the number of members in the community collection.
   *
   * @param array $cacheTagsUsed
   *   Optional array to add the cache tags which affect the calculation. Passed
   *   by reference.
   *
   * @return int
   *   The number of members in the community collection.
   */
  protected function getActiveIopeuMemberCount(array &$cacheTagsUsed = []): int {
    $relevantCacheTags = ['og_membership_list'];
    $cacheTagsUsed = Cache::mergeTags($cacheTagsUsed, $relevantCacheTags);
    $cid = static::CACHE_PREFIX . __FUNCTION__;
    $cachedData = $this->cacheGet($cid);
    if ($cachedData) {
      return $cachedData->data;
    }

    $count = (int) $this->entityTypeManager->getStorage('og_membership')->getQuery()
      ->accessCheck(FALSE)
      ->condition('entity_id', $this->getCommunityCollectionId())
      ->condition('state', 'active')
      ->count()
      ->execute();
    $this->cacheSet(cid: $cid, data: $count, tags: $relevantCacheTags);
    return $count;
  }

  /**
   * Returns the number of the published solutions in the academy collection.
   *
   * @param array $cacheTagsUsed
   *   Optional array to add the cache tags which affect the calculation. Passed
   *   by reference.
   *
   * @return int
   *   The number of the published solutions in the academy collection.
   */
  protected function getPublishedAcademySolutionCount(array &$cacheTagsUsed = []): int {
    $relevantCacheTags = ['node_list:solution'];
    $cacheTagsUsed = Cache::mergeTags($cacheTagsUsed, $relevantCacheTags);
    $cid = static::CACHE_PREFIX . __FUNCTION__;
    $cachedData = $this->cacheGet($cid);
    if ($cachedData) {
      return $cachedData->data;
    }

    $count = (int) $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'solution')
      ->condition('status', 1)
      ->condition('field_state', 'published')
      ->condition('og_audience', $this->getAcademyCollectionId())
      ->count()
      ->execute();
    $this->cacheSet(cid: $cid, data: $count, tags: $relevantCacheTags);
    return $count;
  }

}
