<?php

declare(strict_types=1);

namespace Drupal\interoperable_europe\Plugin\Block;

use Drupal\assessments\AssessmentInterface;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\UseCacheBackendTrait;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
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

  /**
   * Cache tags relevant to the query of ::getPublicSolutionCount.
   *
   * @const string[]
   *
   * @see self::getPublicSolutionCount()
   * @see self::getCacheTags()
   */
  private const array SOLUTION_COUNT_TAGS = ['node_list:solution'];

  /**
   * Cache tags relevant to the query of ::getInteroperableSolutionCount.
   *
   * @const string[]
   *
   * @see self::getInteroperableSolutionCount()
   * @see self::getCacheTags()
   */
  private const array INTEROPERABLE_SOLUTION_COUNT_TAGS = [
    'flagging_list:interoperability',
    'node_list:solution',
  ];

  /**
   * Cache tags relevant to the query of ::getPublicPortalReportSolutionsCount.
   *
   * @const string[]
   *
   * @see self::getPublicPortalReportSolutionsCount()
   * @see self::getCacheTags()
   */
  private const array SOLUTIONS_IN_REPORTS_COUNT_TAGS = [
    'paragraph_list:binding_requirement',
    'assessment_list:report',
    'node_list:solution',
  ];

  /**
   * Cache tags relevant to the query of ::getPublishedReportCount.
   *
   * @const string[]
   *
   * @see self::getPublishedReportCount()
   * @see self::getCacheTags()
   */
  private const array PUBLISHED_REPORT_COUNT_TAGS = ['assessment_list:report'];

  /**
   * Cache tags relevant to the query of ::getActiveIopeuMemberCount.
   *
   * @const string[]
   *
   * @see self::getActiveIopeuMemberCount()
   * @see self::getCacheTags()
   */
  private const array IOPEU_COMMUNITY_MEMBER_COUNT_TAGS = [
    'og_membership_list',
  ];

  /**
   * Cache tags relevant to the query of ::getPublishedAcademySolutionCount.
   *
   * @const string[]
   *
   * @see self::getPublishedAcademySolutionCount()
   * @see self::getCacheTags()
   */
  private const array ACADEMY_SOLUTION_COUNT_TAGS = ['node_list:solution'];

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
  public function build(): array {
    $rows = [];
    $build['table'] = ['#type' => 'table', '#rows' => &$rows];

    // Number of public and currently published solutions.
    $rows['non-archived-sol'] = [
      $this->t('Total number of non-archived interoperability Solutions on the Portal'),
      $this->getPublicSolutionCount('published'),
    ];
    // Number of public archived solutions.
    $rows['archived-sol'] = [
      $this->t('Total number of archived interoperability Solutions on the Portal'),
      $this->getPublicSolutionCount('archived'),
    ];
    // Number of Interoperable Europe Solutions.
    $rows['interop-sol'] = [
      $this->t('Number of Interoperable Europe Solutions'),
      $this->getInteroperableSolutionCount(),
    ];
    // Portal Solutions that have been mentioned in assessment reports.
    $rows['sols-mentioned-in-report'] = [
      $this->t('Number of Portal Solutions that have been mentioned in an assessment'),
      $this->getPublicPortalReportSolutionsCount(),
    ];
    // Number of published assessment reports.
    $rows['published-reports'] = [
      $this->t('Number of published interoperability assessment reports'),
      $this->getPublishedReportCount(),
    ];
    // Number of IOPEU Community members.
    $rows['active-iopeu-members'] = [
      $this->t('Number of IOPEU Community members'),
      $this->getActiveIopeuMemberCount(),
    ];
    // Number of eLearning courses published on the Portal.
    $rows['elearning-courses'] = [
      $this->t('Number of eLearning courses published on the portal'),
      $this->getPublishedAcademySolutionCount(),
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return Cache::mergeTags(
      parent::getCacheTags(),
      static::SOLUTION_COUNT_TAGS,
      static::INTEROPERABLE_SOLUTION_COUNT_TAGS,
      static::SOLUTIONS_IN_REPORTS_COUNT_TAGS,
      static::PUBLISHED_REPORT_COUNT_TAGS,
      static::IOPEU_COMMUNITY_MEMBER_COUNT_TAGS,
      static::ACADEMY_SOLUTION_COUNT_TAGS,
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
  protected function getAssessmentAssetCollectorId(): string {
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
   *
   * @return int
   *   Number of the public collections with the specified state.
   */
  protected function getPublicSolutionCount(string $workflowState): int {
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
      ->condition('og_audience', $this->getAssessmentAssetCollectorId(), '<>')
      ->count()
      ->execute();
    $this->cacheSet(cid: $cid, data: $count, tags: static::SOLUTION_COUNT_TAGS);
    return $count;
  }

  /**
   * Returns the number of the public collections having the interoperable flag.
   *
   * @return int
   *   Number of the public collections having the interoperable flag.
   */
  protected function getInteroperableSolutionCount(): int {
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
      ->condition('nfd.status', 1)
      ->condition('nfd.default_langcode', 1);

    $count = (int) $query->countQuery()->execute()->fetchField();
    $this->cacheSet(cid: $cid, data: $count, tags: static::INTEROPERABLE_SOLUTION_COUNT_TAGS);
    return $count;
  }

  /**
   * Returns the number of the public collections having the interoperable flag.
   *
   * @return int
   *   Number of the public collections having the interoperable flag.
   */
  protected function getPublicPortalReportSolutionsCount(): int {
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
      ->condition('a.og_audience_target_id', $this->getAssessmentAssetCollectorId(), '<>')
      // Only published assessment repo.
      ->condition('ad.status', 1);

    $count = (int) $this->database->select($query)->countQuery()->execute()->fetchField();
    $this->cacheSet(cid: $cid, data: $count, tags: static::SOLUTIONS_IN_REPORTS_COUNT_TAGS);
    return $count;
  }

  /**
   * Returns the number of published assessment reports.
   *
   * @return int
   *   The number of published assessment reports.
   */
  protected function getPublishedReportCount(): int {
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
    $this->cacheSet(cid: $cid, data: $count, tags: static::PUBLISHED_REPORT_COUNT_TAGS);
    return $count;
  }

  /**
   * Returns the number of members in the community collection.
   *
   * @return int
   *   The number of members in the community collection.
   */
  protected function getActiveIopeuMemberCount(): int {
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
    $this->cacheSet(cid: $cid, data: $count, tags: static::IOPEU_COMMUNITY_MEMBER_COUNT_TAGS);
    return $count;
  }

  /**
   * Returns the number of the published solutions in the academy collection.
   *
   * @return int
   *   The number of the published solutions in the academy collection.
   */
  protected function getPublishedAcademySolutionCount(): int {
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
    $this->cacheSet(cid: $cid, data: $count, tags: static::ACADEMY_SOLUTION_COUNT_TAGS);
    return $count;
  }

}
