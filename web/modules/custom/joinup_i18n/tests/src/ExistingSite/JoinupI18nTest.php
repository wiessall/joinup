<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_i18n\ExistingSite;

use Drupal\joinup_collection\JoinupCollectionHelper;
use Drupal\joinup_i18n\Hook\JoinupI18nHooks;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;
use Drupal\tmgmt\JobInterface;
use Drupal\tmgmt_ec_etranslation\Plugin\tmgmt\Translator\ETranslationTranslator;
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
