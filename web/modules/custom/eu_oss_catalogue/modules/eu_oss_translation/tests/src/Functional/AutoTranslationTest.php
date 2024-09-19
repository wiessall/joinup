<?php

declare(strict_types=1);

namespace Drupal\Tests\eu_oss_translation\Functional;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\Queue\QueueWorkerInterface;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\eu_oss_translation\Plugin\QueueWorker\TranslationRequester;
use Drupal\Tests\eu_oss_catalogue\Functional\FetchTestBase;
use Drupal\tmgmt\TranslatorInterface;
use Drupal\tmgmt_ec_etranslation\Plugin\QueueWorker\TranslationQueueWorker;

/**
 * Tests auto translation.
 *
 * @group eu_oss_catalogue
 * @group eu_oss_translation
 */
class AutoTranslationTest extends FetchTestBase {

  /**
   * The translator plugin.
   *
   * @var \Drupal\tmgmt\TranslatorInterface
   */
  protected TranslatorInterface $translator;

  /**
   * The queue factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected QueueFactory $queueFactory;

  /**
   * Queue worker.
   *
   * @var \Drupal\Core\Queue\QueueWorkerManagerInterface
   */
  protected QueueWorkerManagerInterface $queueWorkerManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'dblog',
    'editor',
    'eu_oss_catalogue_test',
    'language',
    'markdown_easy',
    'tmgmt',
    'tmgmt_content',
    'tmgmt_ec_etranslation',
    'tmgmt_ec_etranslation_test',
    'http_request_mock',
    'eu_oss_translation',
    'eu_oss_translation_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected array $controlledVocabTerms = [
    'oss_licence' => ['AGPL-3.0-or-later', 'BSD-3-Clause', 'EUPL-1.2', 'MIT'],
    'oss_category' => [
      'data-analytics',
      'data-collection',
      'digital-citizenship',
      'nothing',
      'website-builder',
    ],
    'oss_software_type' => [
      'standalone/other',
    ],
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->queueFactory = $this->container->get('queue');
    $this->queueWorkerManager = $this->container->get('plugin.manager.queue_worker');
    $this->entityTypeManager = $this->container->get('entity_type.manager');

    // Overwrite directory.
    $this->dir = __DIR__;
  }

  /**
   * Tests translation.
   */
  public function testTranslation(): void {
    $jobBuilderWorker = $this->queueWorkerManager->createInstance(TranslationRequester::ID);
    $jobBuilderQueue = $this->queueFactory->get(TranslationRequester::ID);

    $tmgProviderWorker = $this->queueWorkerManager->createInstance(TranslationQueueWorker::ID);
    $tmgProviderQueue = $this->queueFactory->get(TranslationQueueWorker::ID);

    $this->fetchSolutions('developers_italia', 5);

    $this->assertEquals(5, $jobBuilderQueue->numberOfItems());
    $this->processQueue($jobBuilderWorker, $jobBuilderQueue, 5);
    $this->assertEquals(0, $jobBuilderQueue->numberOfItems());

    $this->assertEquals(11, $tmgProviderQueue->numberOfItems());
    $this->processQueue($tmgProviderWorker, $tmgProviderQueue, 11);
    $this->assertEquals(0, $tmgProviderQueue->numberOfItems());

    $this->entityTypeManager->getStorage('node')->resetCache();

    $this->assertOssSolution('GitHub solution 1', 'en');
    $this->assertOssSolution('Project 1.1', 'it');
    $this->assertOssSolution('Project 1.2', 'de');
    $this->assertOssSolution('Project 1.4', 'de');
    $this->assertOssSolution('Project 1', 'de');
  }

  /**
   * Processes queue.
   *
   * @param \Drupal\Core\Queue\QueueWorkerInterface $queueWorker
   *   Queue worker.
   * @param \Drupal\Core\Queue\QueueInterface $queue
   *  Queue.
   * @param int $limit
   *   Limit of queue elements.
   */
  protected function processQueue(QueueWorkerInterface $queueWorker, QueueInterface $queue, int $limit = 100): void {
    $processed = 0;
    while (($item = $queue->claimItem()) && $limit > $processed) {
      try {
        $queueWorker->processItem($item->data);
        $queue->deleteItem($item);
      }
      catch (\Exception $e) {
        $queue->releaseItem($item);
        throw $e;
      }

      $processed++;
    }
  }

}
