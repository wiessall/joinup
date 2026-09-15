<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_i18n\ExistingSite;

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\joinup_collection\JoinupCollectionHelper;
use Drupal\joinup_i18n\Hook\JoinupI18nHooks;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\meta_entity\Entity\MetaEntity;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;
use Drupal\tmgmt\JobInterface;
use Drupal\tmgmt\JobItemInterface;
use Drupal\tmgmt_ec_etranslation\Plugin\tmgmt\Translator\ETranslationTranslator;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for Joinup i18n.
 */
#[Group('i18n')]
#[RunTestsInSeparateProcesses]
final class JoinupI18nTest extends JoinupExistingSiteTestBase {

  private const array TARGET_LANGCODES = ['fr', 'gr', 'pt'];

  /**
   * The base continuous settings.
   *
   * @var array{content: array<string, array{enabled: true, bundles: array<string, true>}>}
   */
  protected array $continuousSettingsBase;

  /**
   * Testing continuous jobs keyed by their target language code.
   */
  protected array $jobs = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->continuousSettingsBase = \Drupal::service(JoinupI18nHooks::class)->getContinuousSettings();

    // Create continuous jobs for all target languages.
    foreach (self::TARGET_LANGCODES as $target) {
      $job = tmgmt_job_create('en', $target, 0, [
        'label' => "Joinup: en > $target",
        'translator' => ETranslationTranslator::ID,
        'job_type' => JobInterface::TYPE_CONTINUOUS,
        'state' => JobInterface::STATE_CONTINUOUS,
        'continuous_settings' => $this->continuousSettingsBase,
      ]);
      $job->save();
      $this->jobs[$target] = $job;
      $this->markEntityForCleanup($job);
    }
  }

  /**
   * Tests continuous settings updates when new bundles are added/removed.
   *
   * @legacy-covers \Drupal\joinup_i18n\EventSubscriber\ConfigSubscriber::onConfigSave
   */
  public function testConfigUpdate(): void {
    $nodeType = NodeType::create([
      'type' => 'test_language_content_settings',
      'name' => 'Test language content settings',
    ]);
    $nodeType->save();
    $this->markEntityForCleanup($nodeType);

    // Bundle without joinup_i18n settings doesn't affect continuous settings.
    $this->assertContinuousSettingsBase();

    $config = $this->getConfigFactory()->getEditable('joinup_i18n.settings');
    $settings = $original = $config->get('tmgmt_content');

    // Check the enabling translation updates continuous settings.
    $settings['node'][] = 'test_language_content_settings';
    $config->set('tmgmt_content', $settings)->save();
    $this->assertContinuousSettingsExtended();

    // Check the disabling translation updates continuous settings.
    $config->set('tmgmt_content', $original)->save();
    $this->assertContinuousSettingsBase();
  }

  /**
   * Tests continuous jobs updates when a new language is added/removed.
   *
   * @legacy-covers \Drupal\joinup_i18n\Hook\JoinupI18nHooks::onLanguageInsert
   * @legacy-covers \Drupal\joinup_i18n\Hook\JoinupI18nHooks::onLanguageDelete
   */
  public function testLanguageCreationAndRemoval(): void {
    $this->asserContinuousJobsBase();
    ConfigurableLanguage::createFromLangcode('af')->save();
    $this->assertContinuousJobsExtended();
    ConfigurableLanguage::load('af')->delete();
    $this->asserContinuousJobsBase();
  }

  /**
   * Tests continuous job items creation.
   *
   * @legacy-covers \Drupal\joinup_i18n\EventSubscriber\ScheduleJobItemSubscriber::scheduleJobItemCreation
   * @legacy-covers \Drupal\joinup_i18n\Plugin\QueueWorker\JobItemCreationWorker
   */
  public function testContinuousJobItemsCreation(): void {
    $nodeType = NodeType::create([
      'type' => 'test_language_content_settings',
      'name' => 'Test language content settings',
    ]);
    $nodeType->save();
    $this->markEntityForCleanup($nodeType);

    // No job items should exist yet.
    $this->assertJobItems();

    $config = $this->getConfigFactory()->getEditable('joinup_i18n.settings');
    $settings = $config->get('tmgmt_content');

    $node = $this->createNode([
      'type' => 'test_language_content_settings',
      'title' => 'Test node',
    ]);
    ContentLanguageSettings::create([
      'id' => 'node.test_language_content_settings',
      'target_entity_type_id' => 'node',
      'target_bundle' => 'test_language_content_settings',
    ])->setThirdPartySetting('content_translation', 'enabled', TRUE)
      ->save();

    // A node has been created, but its bundle is not configured yet.
    $this->assertJobItems();

    // Configure the bundle to be translated and re-save the node.
    $settings['node'][] = 'test_language_content_settings';
    $config->set('tmgmt_content', $settings)->save();
    $node->save();

    $this->assertJobItems(array_fill_keys(self::TARGET_LANGCODES, $node->id()));
  }

  /**
   * Tests that archived content is not translated.
   *
   * @legacy-covers \Drupal\joinup_i18n\EventSubscriber\ScheduleJobItemSubscriber::scheduleJobItemCreation
   */
  public function testNonTranslatableContent(): void {
    $node = $this->createNode([
      'type' => 'solution',
      'og_audience' => \Drupal::service('entity.repository')->loadEntityByUuid('node', JoinupCollectionHelper::JOINUP_COLLECTION_UUID),
      'title' => 'Testing solution',
      'field_state' => 'proposed',
    ]);

    // Unpublished content is not scheduled for translation.
    $this->assertJobItems();

    // Published content is scheduled for translation.
    $node->set('field_state', 'published')->save();
    $this->assertJobItems(array_fill_keys(self::TARGET_LANGCODES, $node->id()));

    // Archived content is not scheduled for translation.
    $node->set('field_state', 'archived')->save();
    $this->assertJobItems();

    // Unarchiving content it schedules it for translation.
    $node->set('field_state', 'published')->save();
    $this->assertJobItems(array_fill_keys(self::TARGET_LANGCODES, $node->id()));

    // Unpublishing content, it doesn't schedule it for translation.
    $node->set('field_state', 'draft_after_published')->save();
    $this->assertJobItems();
  }

  /**
   * Tests that a forward draft of published content is not scheduled.
   *
   * A draft on a published node is a non-default forward revision; TMGMT would
   * translate it as unpublished content. Publishing the draft re-schedules it.
   *
   * @legacy-covers \Drupal\joinup_i18n\EventSubscriber\ScheduleJobItemSubscriber::scheduleJobItemCreation
   */
  public function testForwardDraftIsNotScheduled(): void {
    $node = $this->createNode([
      'type' => 'solution',
      'og_audience' => \Drupal::service('entity.repository')->loadEntityByUuid('node', JoinupCollectionHelper::JOINUP_COLLECTION_UUID),
      'title' => 'Forward draft scheduling',
      'field_state' => 'proposed',
    ]);

    // Publishing schedules the content for translation.
    $node->setNewRevision();
    $node->set('field_state', 'published')->save();
    $this->assertJobItems(array_fill_keys(self::TARGET_LANGCODES, $node->id()));

    // A draft on the published node is a forward revision, which must not be
    // scheduled while the published revision remains the default.
    $node->setNewRevision();
    $node->set('field_state', 'draft_after_published')->save();
    $this->assertJobItems();

    // Publishing the draft schedules it again, against the default revision.
    $node->setNewRevision();
    $node->set('field_state', 'published')->save();
    $this->assertJobItems(array_fill_keys(self::TARGET_LANGCODES, $node->id()));
  }

  /**
   * Tests that job items are aborted when their source content changes.
   *
   * Also checks the opposite: a save that leaves the source content unchanged
   * (accepting a translation) must keep the other languages' items open.
   *
   * @legacy-covers \Drupal\joinup_i18n\Hook\JoinupI18nHooks::abortOutdatedJobItems
   * @legacy-covers \joinup_group_node_presave
   */
  public function testOutdatedJobItemsAreAborted(): void {
    $node = $this->createNode([
      'type' => 'solution',
      'og_audience' => \Drupal::service('entity.repository')->loadEntityByUuid('node', JoinupCollectionHelper::JOINUP_COLLECTION_UUID),
      'title' => 'Outdated translation',
      'field_state' => 'proposed',
    ]);

    // Publish: a first set of continuous job items is scheduled.
    $node->setNewRevision();
    $node->set('field_state', 'published')->save();
    $firstItems = $this->jobItemIds($node);
    $this->assertNotEmpty($firstItems);

    // The items start inactive (submit-on-cron); submit one to eTranslation
    // so both an inactive and an active item are covered.
    $itemStorage = \Drupal::entityTypeManager()->getStorage('tmgmt_job_item');
    $items = array_values($itemStorage->loadMultiple($firstItems));
    $items[0]->setState(JobItemInterface::STATE_ACTIVE);

    // Creating a draft adds a pending forward revision, so the open items (both
    // inactive and active) are aborted and nothing is scheduled.
    $node->setNewRevision();
    $node->set('field_state', 'draft_after_published')->save();
    $itemStorage->resetCache($firstItems);
    foreach ($itemStorage->loadMultiple($firstItems) as $item) {
      $this->assertTrue($item->isAborted());
    }
    $this->assertEmpty($this->jobItemIds($node, [JobItemInterface::STATE_INACTIVE, JobItemInterface::STATE_ACTIVE]));

    // Publishing the draft schedules a fresh translation of the new content.
    $node->setNewRevision();
    $node->set('field_state', 'published')->save();
    $freshItems = $this->jobItemIds($node, [JobItemInterface::STATE_INACTIVE, JobItemInterface::STATE_ACTIVE]);
    $this->assertGreaterThan(1, count($freshItems));
    $this->assertEmpty(array_intersect($firstItems, $freshItems));

    // Clearing the logo makes joinup_group_node_presave() assign a fresh
    // fixture image.
    $node->setNewRevision();
    $node->set('logo', [])->save();
    $logo = $node->get('logo')->first();
    $this->assertNotEmpty($logo->target_id);

    // The logo genuinely changed (a different file), so the previous items
    // are correctly aborted and a newer set is scheduled.
    $itemStorage->resetCache($freshItems);
    foreach ($itemStorage->loadMultiple($freshItems) as $item) {
      $this->assertTrue($item->isAborted());
    }
    $newestItems = $this->jobItemIds($node, [JobItemInterface::STATE_INACTIVE, JobItemInterface::STATE_ACTIVE]);
    $this->assertGreaterThan(1, count($newestItems));

    // Accepting one language's translation re-saves the source node without
    // changing the source content, so the other languages' items stay open.
    $acceptedItem = $itemStorage->load(array_shift($newestItems));
    $dataService = \Drupal::service('tmgmt.data');
    $translation = [];
    foreach ($dataService->filterTranslatable($acceptedItem->getData()) as $key => $data) {
      $translation[$key]['#text'] = 'xx: ' . $data['#text'];
    }
    $acceptedItem->addTranslatedData($dataService->unflatten($translation));
    $this->assertTrue($acceptedItem->isAccepted());

    $itemStorage->resetCache($newestItems);
    $otherItems = $itemStorage->loadMultiple($newestItems);
    $this->assertCount(count($newestItems), $otherItems);
    foreach ($otherItems as $item) {
      $this->assertFalse($item->isAborted());
    }

    // Clean up the job items created for this node.
    $itemStorage->delete($itemStorage->loadMultiple($this->jobItemIds($node)));
  }

  /**
   * Tests the job item messages page and its operation link.
   *
   * @legacy-covers \Drupal\joinup_i18n\Hook\JoinupI18nHooks::entityOperation
   */
  public function testJobItemMessagesPage(): void {
    $node = $this->createNode([
      'type' => 'solution',
      'og_audience' => \Drupal::service('entity.repository')->loadEntityByUuid('node', JoinupCollectionHelper::JOINUP_COLLECTION_UUID),
      'title' => 'Job item messages',
      'field_state' => 'proposed',
    ]);
    $node->setNewRevision();
    $node->set('field_state', 'published')->save();

    $itemStorage = \Drupal::entityTypeManager()->getStorage('tmgmt_job_item');
    $itemId = $this->jobItemIds($node)[0];
    $item = $itemStorage->load($itemId);
    $item->addMessage('A message only on this job item.');

    // The list builder links a "Messages" operation.
    $operations = \Drupal::entityTypeManager()->getListBuilder('tmgmt_job_item')->getOperations($item);
    $this->assertArrayHasKey('job_item_messages', $operations);
    $url = $operations['job_item_messages']['url'];
    $this->assertSame("/admin/tmgmt/items/$itemId/messages", $url->toString());
    $this->assertFalse($url->access(new AnonymousUserSession()));

    // The page lists the item's own messages.
    $build = Views::getView('joinup_job_item_messages')->preview('page', [$itemId]);
    $html = (string) \Drupal::service('renderer')->renderInIsolation($build);
    $this->assertStringContainsString('A message only on this job item.', $html);

    // Clean up the job items created for this node.
    $itemStorage->delete($itemStorage->loadMultiple($this->jobItemIds($node)));
  }

  /**
   * Returns the job item ids for a node, optionally filtered by state.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The source node.
   * @param int[]|null $states
   *   The job item states to filter on, or NULL for any state.
   *
   * @return string[]
   *   The matching job item ids, sorted.
   */
  private function jobItemIds(NodeInterface $node, ?array $states = NULL): array {
    $query = \Drupal::entityTypeManager()->getStorage('tmgmt_job_item')->getQuery()
      ->accessCheck(FALSE)
      ->condition('plugin', 'content')
      ->condition('item_type', $node->getEntityTypeId())
      ->condition('item_id', (string) $node->id());
    if ($states !== NULL) {
      $query->condition('state', $states, 'IN');
    }
    $ids = array_values($query->execute());
    sort($ids);
    return $ids;
  }

  /**
   * Tests that entities without translatable content are not scheduled.
   *
   * @legacy-covers \Drupal\joinup_i18n\EventSubscriber\ScheduleJobItemSubscriber::scheduleJobItemCreation
   */
  public function testNonTranslatableFields(): void {
    $collection = $this->createNode([
      'type' => 'collection',
      'field_state' => 'published',
    ]);

    $settings = MetaEntity::loadOrCreate('collection_settings', $collection);
    $this->markEntityForCleanup($settings);

    $jobItemStorage = \Drupal::entityTypeManager()->getStorage('tmgmt_job_item');
    $jobItemStorage->delete($jobItemStorage->loadByProperties([
      'plugin' => 'content',
      'item_type' => 'meta_entity',
      'item_id' => $settings->id(),
    ]));

    $settings->set('overview_label', '')->save();

    $jobItemIds = \Drupal::entityQuery('tmgmt_job_item')->accessCheck(FALSE)
      ->condition('plugin', 'content')
      ->condition('item_type', 'meta_entity')
      ->condition('item_id', $settings->id())
      ->execute();
    $this->assertEmpty($jobItemIds);

    // A whitespace-only value has a word count of 0, same as an empty one.
    $settings->set('label_join', ' ')->save();

    $jobItemIds = \Drupal::entityQuery('tmgmt_job_item')->accessCheck(FALSE)
      ->condition('plugin', 'content')
      ->condition('item_type', 'meta_entity')
      ->condition('item_id', $settings->id())
      ->execute();
    $this->assertEmpty($jobItemIds);
  }

  /**
   * Tests that remote videos are never handed to machine translation.
   */
  public function testRemoteVideoIsNotMachineTranslated(): void {
    $media = \Drupal::entityTypeManager()->getStorage('media')->create([
      'bundle' => 'remote_video',
      'name' => 'Joinup promotional video',
      'media_oembed_video' => 'https://www.youtube.com/watch?v=BA1jY8LJ8tM',
    ]);

    $definitions = $media->getFieldDefinitions();
    $this->assertTrue($definitions['media_oembed_video']->isTranslatable());
    foreach (['name', 'thumbnail', 'created', 'changed', 'status', 'uid', 'path', 'metatag'] as $fieldName) {
      $this->assertFalse($definitions[$fieldName]->isTranslatable());
    }

    $dataHelper = \Drupal::service('tmgmt.data');
    $data = \Drupal::service('plugin.manager.tmgmt.source')
      ->createInstance('content')
      ->extractTranslatableData($media);

    $wordCount = array_sum(array_map(
      fn(array $item): int => $dataHelper->wordCount($item['#text']),
      $dataHelper->filterTranslatable($data),
    ));
    $this->assertSame(0, $wordCount);
  }

  /**
   * Asserts that job items exist for the expected node IDs.
   *
   * @param array $expectedNids
   *   The expected node IDs.
   */
  protected function assertJobItems(array $expectedNids = []): void {
    $query = \Drupal::database()->select('tmgmt_job_item', 'tji');
    $query->innerJoin('tmgmt_job', 'tj', 'tj.tjid = tji.tjid');
    $result = $query->condition('tj.source_language', 'en')
      ->condition('tji.plugin', 'content')
      ->condition('tji.item_type', 'node')
      ->fields('tj', ['target_language'])
      ->fields('tji', ['item_id'])
      ->orderBy('tj.target_language')
      ->execute()
      ->fetchAllKeyed();

    ksort($expectedNids);
    $this->assertEquals($expectedNids, $result);

    // Next assertion to start on clean.
    if ($result) {
      $storage = \Drupal::entityTypeManager()->getStorage('tmgmt_job_item');
      $storage->delete($storage->loadMultiple());
    }
  }

  /**
   * Asserts that the base continuous settings are as expected.
   */
  protected function assertContinuousSettingsBase(): void {
    $this->continuousSettingsAssertionHelper(FALSE);
  }

  /**
   * Asserts that the extended continuous settings are as expected.
   */
  protected function assertContinuousSettingsExtended(): void {
    $this->continuousSettingsAssertionHelper(TRUE);
  }

  /**
   * Provides reusable code for continuous settings assertions.
   *
   * @param bool $extended
   *   Whether to assert the extended settings or the base settings.
   */
  protected function continuousSettingsAssertionHelper(bool $extended): void {
    $expectedSettings = $this->continuousSettingsBase;
    if ($extended) {
      $expectedSettings['content']['node']['bundles']['test_language_content_settings'] = 1;
    }

    $actualSettings = unserialize(
      \Drupal::entityQueryAggregate('tmgmt_job')->accessCheck(FALSE)
        ->groupBy('continuous_settings')
        ->condition('job_type', JobInterface::TYPE_CONTINUOUS)
        ->condition('state', JobInterface::STATE_CONTINUOUS)
        ->condition('translator', ETranslationTranslator::ID)
        ->execute()[0]['continuous_settings'],
      ['allowed_classes' => FALSE],
    );

    $this->assertEquals($expectedSettings, $actualSettings);
  }

  /**
   * Asserts that new continuous jobs were created for a new non-EU language.
   */
  protected function assertContinuousJobsExtended(): void {
    $this->continuousJobsExpansionAssertionHelper(TRUE);
  }

  /**
   * Asserts that there are no continuous jobs apart from EU languages.
   */
  protected function asserContinuousJobsBase(): void {
    $this->continuousJobsExpansionAssertionHelper(FALSE);
  }

  /**
   * Provides reusable code to test continuous jobs expansion/contraction.
   *
   * @param bool $checkExists
   *   The assertion to perform.
   */
  protected function continuousJobsExpansionAssertionHelper(bool $checkExists): void {
    $query = \Drupal::entityQuery('tmgmt_job')->accessCheck(FALSE)
      ->condition('translator', ETranslationTranslator::ID)
      ->condition('job_type', JobInterface::TYPE_CONTINUOUS)
      ->condition('state', JobInterface::STATE_CONTINUOUS)
      ->count();
    $or = $query->orConditionGroup()
      ->condition('source_language', 'af')
      ->condition('target_language', 'af');
    $this->assertSame($checkExists, (bool) $query->condition($or)->execute());
  }

}
