<?php

declare(strict_types = 1);

namespace Drupal\Tests\solution\Functional;

use Drupal\collection\Entity\CollectionInterface;
use Drupal\Core\CronInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\solution\Entity\SolutionInterface;
use Drupal\solution\Plugin\QueueWorker\SolutionContentArchiverQueue;
use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;
use Drupal\Tests\joinup_test\Traits\ConfigTestTrait;
use Drupal\Tests\sparql_entity_storage\Traits\SparqlConnectionTrait;
use Drupal\rdf_entity\Entity\Rdf;
use Drupal\rdf_entity\Entity\RdfEntityType;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Assert;

/**
 * Tests solution affiliation.
 *
 * @group solution
 */
class SolutionArchivalTest extends JoinupExistingSiteTestBase {

  /**
   * Tests archiving of a solution.
   */
  public function testSolutionArchival(): void {
    $user = User::load(1);
    \Drupal::currentUser()->setAccount($user);

    $storage = \Drupal::entityTypeManager()->getStorage('rdf_entity');
    $solution = $storage->load(
      'http://data.europa.eu/w21/8695037c-81e6-412c-805f-40cf3ce592e0'
    );

    $this->assertInstanceOf(SolutionInterface::class, $solution);

    $ids = $solution->getGroupContentIds();

    //SolutionContentArchiverQueue::getSolutionGroupContent($solution);

    //return;

    for ($i = 0; $i < 1; $i++) {
      $solution = $this->getAnArchivableSolution();
      $this->assertInstanceOf(SolutionInterface::class, $solution);
      $this->assertTrue($solution->isTargetWorkflowStateAllowed('archived'));
      $solution->setWorkflowState('archived')->save();
    }

    $queue = \Drupal::queue('solution_archive_related_content');
    $cron = \Drupal::service('cron');
    $this->assertInstanceOf(CronInterface::class, $cron);
    $this->assertGreaterThan(0, $queue->numberOfItems());
    while ($queue->numberOfItems() > 0) {
      $cron->run();
    }

    // Emails to moderators are sent in another cron execution.
    // $cron->run();
  }

  protected function getAnArchivableSolution(): ?SolutionInterface {
    $storage = \Drupal::entityTypeManager()->getStorage('rdf_entity');
    $collections = $storage->loadByProperties(['rid' => 'collection']);
    while ($collection = array_shift($collections)) {
      $this->assertInstanceOf(CollectionInterface::class, $collection);
      if ($solution = $this->getFirstArchivableSolution($collection)) {
        break;
      }
    }

    return $solution instanceof SolutionInterface ? $solution : NULL;
  }

  protected function getCollectionWithArchivableSolution(): ?CollectionInterface {
    $storage = \Drupal::entityTypeManager()->getStorage('rdf_entity');
    $collections = $storage->loadByProperties(['rid' => 'collection']);
    while ($collection = array_shift($collections)) {
      $this->assertInstanceOf(CollectionInterface::class, $collection);
      if ($this->getFirstArchivableSolution($collection)) {
        break;
      }
    }

    return $collection instanceof CollectionInterface ? $collection : NULL;
  }

  protected function getFirstArchivableSolution(CollectionInterface $collection): ?SolutionInterface {
    $solutions = array_filter(
      $collection->referencedEntities(),
      fn (EntityInterface $entity): bool => $entity instanceof SolutionInterface && $entity->isPublished() && $entity->getWorkflowState() !== 'archived',
    );
    $this->assertContainsOnlyInstancesOf(SolutionInterface::class, $solutions);
    // Pick first solution.
    $solution = reset($solutions);
    return $solution instanceof SolutionInterface ? $solution : NULL;
  }

}
