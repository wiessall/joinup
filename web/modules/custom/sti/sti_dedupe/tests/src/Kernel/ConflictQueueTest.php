<?php

declare(strict_types=1);

namespace Drupal\Tests\sti_dedupe\Kernel;

use Drupal\Core\Queue\QueueInterface;
use Drupal\sti_dedupe\ContentModel;
use Drupal\sti_dedupe\Hook\DedupeHooks;
use Drupal\sti_dedupe\Plugin\QueueWorker\ConflictRefreshWorker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that content changes queue conflict refreshes, and that they run.
 *
 * If these hooks stop firing nothing errors: conflicts simply stop being
 * found after imports. That is the silent failure covered here.
 */
#[CoversClass(DedupeHooks::class)]
#[CoversClass(ConflictRefreshWorker::class)]
#[Group('sti_dedupe')]
final class ConflictQueueTest extends StiDedupeKernelTestBase {

  /**
   * Re-importing a source queues the innovations derived from it.
   */
  public function testSourceChangeQueuesItsInnovations(): void {
    $one = $this->collection('One');
    $two = $this->collection('Two');
    $readiness = $this->property('readiness', self::READINESS);
    $a = $this->solution('A', [$this->claim($one, $readiness, 'TRL 5')]);
    $b = $this->solution('B', [$this->claim($two, $this->property('maturity', self::READINESS), 'TRL 5')]);
    $innovation = $this->innovation('Innovation', [$a, $b]);
    $this->queue()->deleteQueue();

    $this->reimport($a, $one, [$this->claim($one, $readiness, 'TRL 7')]);

    $this->assertSame(1, $this->queue()->numberOfItems());
    $this->runQueue();
    $this->assertArrayHasKey(self::READINESS, $this->resolutions($innovation));
  }

  /**
   * An innovation whose sources change queues itself; other edits do not.
   */
  public function testInnovationQueuesItselfOnlyWhenSourcesChange(): void {
    $a = $this->solution('A');
    $b = $this->solution('B');
    $innovation = $this->innovation('Innovation', [$a]);
    $this->queue()->deleteQueue();

    $innovation = $this->reload($innovation);
    $innovation->setTitle('Renamed');
    $innovation->save();
    $this->assertSame(0, $this->queue()->numberOfItems());

    $innovation->get(ContentModel::DERIVED_FROM)->appendItem([
      'target_id' => $b->id(),
      'target_revision_id' => $b->getRevisionId(),
    ]);
    $innovation->save();
    $this->assertSame(1, $this->queue()->numberOfItems());
  }

  /**
   * Mapping edits request one full refresh, however many terms change.
   */
  public function testMappingChangesCollapseIntoOneFullRefresh(): void {
    $innovation = $this->innovation('Innovation', [$this->solution('A')]);
    $first = $this->property('readiness', NULL);
    $second = $this->property('maturity', NULL);
    $this->queue()->deleteQueue();

    $first->set(ContentModel::ATIO_PROPERTY, self::READINESS)->save();
    $second->set(ContentModel::ATIO_PROPERTY, self::READINESS)->save();
    $this->assertSame(1, $this->queue()->numberOfItems(), 'Two mapping edits, one full refresh.');

    // Fanning out clears the request and queues every innovation.
    $this->runQueue();
    $this->assertNull($this->container->get('state')->get('sti_dedupe.full_refresh_pending'));

    // A mapping change after the fan-out requests a new full pass.
    $first->set(ContentModel::ATIO_PROPERTY, self::START_YEAR)->save();
    $this->assertSame(1, $this->queue()->numberOfItems());
    $this->assertNotNull($innovation->id());
  }

  /**
   * Processes the queue until it is empty.
   */
  private function runQueue(): void {
    $worker = $this->container->get('plugin.manager.queue_worker')->createInstance(ConflictRefreshWorker::QUEUE);
    $queue = $this->queue();
    $guard = 0;
    while (($item = $queue->claimItem()) && $guard++ < 100) {
      $worker->processItem($item->data);
      $queue->deleteItem($item);
    }
  }

  /**
   * Returns the conflict refresh queue.
   */
  private function queue(): QueueInterface {
    return $this->container->get('queue')->get(ConflictRefreshWorker::QUEUE);
  }

}
