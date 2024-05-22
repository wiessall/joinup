<?php

declare(strict_types=1);

namespace Drupal\Tests\eu_oss_vitality_index\Unit;

use Drupal\eu_oss_vitality_index\VitalityIndexProcessor;
use Drupal\Tests\UnitTestCase;
use Gitonomy\Git\Commit;
use Gitonomy\Git\Log;
use Gitonomy\Git\Reference\Tag;
use Gitonomy\Git\ReferenceBag;
use Gitonomy\Git\Repository;

/**
 * Tests VitalityIndexProcessor.
 *
 * @coversDefaultClass \Drupal\eu_oss_vitality_index\VitalityIndexProcessor
 *
 * @group eu_oss_catalogue
 * @group eu_oss_vitality_index
 */
class VitalityIndexProcessorTest extends UnitTestCase {

  /**
   * Provides test cases for getPointsPerDay() method.
   *
   * @covers ::getPointsPerDay
   */
  public function testGetPointsPerDay(): void {
    $commits = $this->generateCommits();

    $firstCommit = $commits[array_key_first($commits)];
    $lastCommit = $commits[array_key_last($commits)];

    $firstTag = $this->prophesize(Tag::class);
    $firstTag->getCommitHash()->willReturn($firstCommit->getHash());
    $firstTag->getCommit()->willReturn($firstCommit);

    $lastTag = $this->prophesize(Tag::class);
    $lastTag->getCommitHash()->willReturn($lastCommit->getHash());
    $lastTag->getCommit()->willReturn($lastCommit);

    $referenceBag = $this->prophesize(ReferenceBag::class);
    $referenceBag->getTags()->willReturn([
      $firstTag->reveal(),
      $lastTag->reveal(),
    ]);

    $log = $this->prophesize(Log::class);
    $log->getCommits()->willReturn($commits);

    $repository = $this->prophesize(Repository::class);
    $repository->getHeadCommit()->willReturn($lastCommit);
    $repository->getLog([$lastCommit->getHash()])->willReturn($log);
    $repository->getReferences()->willReturn($referenceBag);
    $repository->getCommit($lastCommit->getHash())->willReturn($lastCommit);

    $expectedPoints = [70, 80, 66, 66, 72, 72, 74,];
    $processor = new VitalityIndexProcessor($repository->reveal(), 7);
    $this->assertEquals($expectedPoints, $processor->getPointsPerDay());
  }

  /**
   * Generates commits.
   *
   * @return Commit[]
   *   Commits.
   */
  protected function generateCommits(): array {
    $commits = [];

    for ($day = 20; $day >= 0; $day--) {
      for ($x = 1; $x <= $day; $x++) {
        $hash = random_bytes(18);

        $commit = $this->prophesize(Commit::class);
        $commit->getHash()->willReturn($hash);
        $commit->getCommitterDate()
          ->willReturn(new \DateTime('- ' . ($day) . 'days'));
        $commit->getParentHashes()->willReturn([2]);
        $commit->getAuthorEmail()->willReturn('Author' . $day . '@dot.com');

        $commits[$hash] = $commit->reveal();
      }
    }

    return $commits;
  }

}
