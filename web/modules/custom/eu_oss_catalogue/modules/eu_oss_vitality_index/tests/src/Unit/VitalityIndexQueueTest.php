<?php

declare(strict_types=1);

namespace Drupal\Tests\eu_oss_vitality_index\Unit;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\eu_oss_vitality_index\VitalityIndexQueue;
use Drupal\node\NodeStorageInterface;
use Prophecy\Argument;

/**
 * Tests VitalityIndexQueue.
 *
 * @coversDefaultClass \Drupal\eu_oss_vitality_index\VitalityIndexQueue
 *
 * @group eu_oss_catalogue
 * @group eu_oss_vitality_index
 */
class VitalityIndexQueueTest extends UnitTestCase {

  /**
   * Test addToQueue() method.
   *
   * @covers ::addToQueue
   * @dataProvider providerForQueue
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function testAddingToQueue(int $numberOfItems, int $numberOfNodes, bool $shouldBeAdded): void {
    $nid = 22381;
    $numberOfAdds = (int) $shouldBeAdded;
    $numberOfErrors = (int) !$shouldBeAdded;

    $query = $this->prophesize(QueryInterface::class);
    $query->accessCheck(FALSE)->willReturn($query);
    $query->condition('type', 'oss_solution')->willReturn($query);
    $query->condition('status', TRUE)->willReturn($query);
    $query->condition('oss_archived', FALSE)->willReturn($query);
    $query->count()->willReturn($query);
    $query->execute()->willReturn($numberOfNodes);
    $nodeStorage = $this->prophesize(NodeStorageInterface::class);
    $nodeStorage->getQuery()->willReturn($query->reveal());
    $entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $entityTypeManager->getStorage('node')->willReturn($nodeStorage->reveal());

    $logger = $this->prophesize(LoggerChannelInterface::class);
    $logger->error(Argument::any())->shouldBeCalledTimes($numberOfErrors);

    $queue = $this->prophesize(QueueInterface::class);
    $queue->createQueue()->shouldBeCalledTimes($numberOfAdds);
    $queue->numberOfItems()->willReturn($numberOfItems)->shouldBeCalledOnce();
    $queue->createItem($nid)->shouldBeCalledTimes($numberOfAdds);
    $queueFactory = $this->prophesize(QueueFactory::class);
    $queueFactory->get(VitalityIndexQueue::QUEUE_NAME)->willReturn($queue);

    $vitalityIndexQueue = new VitalityIndexQueue(
      $entityTypeManager->reveal(),
      $logger->reveal(),
      $queueFactory->reveal()
    );
    $vitalityIndexQueue->addToQueue($nid);
  }

  /**
   * Data provider for testAddingToQueue().
   */
  public static function providerForQueue(): array {
    return [
      'Less queue items than nodes' => [
        'numberOfItems' => 1,
        'numberOfNodes' => 2,
        'shouldBeAdded' => TRUE,
      ],
      'More queue items than nodes' => [
        'numberOfItems' => 2,
        'numberOfNodes' => 1,
        'shouldBeAdded' => FALSE,
      ],
      'Equal queue size and node number' => [
        'numberOfItems' => 3,
        'numberOfNodes' => 3,
        'shouldBeAdded' => TRUE,
      ],
    ];
  }

}
