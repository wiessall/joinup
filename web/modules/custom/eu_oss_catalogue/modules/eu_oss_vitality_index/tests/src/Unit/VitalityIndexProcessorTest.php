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

    $firstTag = $this->generateTag($firstCommit);
    $lastTag = $this->generateTag($lastCommit);
    $referenceBag = $this->generateReferenceBag([$firstTag, $lastTag]);
    $log = $this->generateLog($commits);

    $repository = $this->generateRepository($lastCommit, $log, $referenceBag);

    $expectedPoints = [70, 80, 66, 66, 72, 72, 74,];
    $processor = new VitalityIndexProcessor($repository, 7);
    $this->assertEquals($expectedPoints, $processor->getPointsPerDay());
  }

  /**
   * Tests longevity range.
   *
   * @dataProvider providerLongevityRange
   */
  public function testLongevityRange(int $creationOfLastCommit, int $expectedPoints): void {
    $commit = $this->generateCommit($creationOfLastCommit);
    $tag = $this->generateTag($commit);
    $referenceBag = $this->generateReferenceBag([$tag]);
    $log = $this->generateLog([$commit]);
    $repository = $this->generateRepository($commit, $log, $referenceBag);

    $processor = new VitalityIndexProcessor($repository, 1);
    $this->assertEquals([$expectedPoints], $processor->getPointsPerDay());
  }

  /**
   * Data provider for testLongevityRange().
   */
  public function providerLongevityRange(): array {
    return [
      [100, 4 + 2 + 20 + 20],
      [400, 4 + 2 + 20 + 30],
      [900, 4 + 2 + 20 + 35],
    ];
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
        $commit = $this->generateCommit($day);

        $commits[$commit->getHash()] = $commit;
      }
    }

    return $commits;
  }

  /**
   * Generates a commit.
   *
   * @param int $daysAgo
   *   Defines when it was created.
   *
   * @return \Gitonomy\Git\Commit
   *   Commit.
   */
  protected function generateCommit(int $daysAgo): Commit {
    $hash = random_bytes(18);

    $commit = $this->prophesize(Commit::class);
    $commit->getHash()->willReturn($hash);
    $commit->getCommitterDate()
      ->willReturn(new \DateTime('- ' . ($daysAgo) . 'days'));
    $commit->getParentHashes()->willReturn([2]);
    $commit->getAuthorEmail()->willReturn('Author' . $daysAgo . '@dot.com');

    return $commit->reveal();
  }

  /**
   * Generates tag.
   *
   * @param \Gitonomy\Git\Commit $commit
   *   Commit.
   *
   * @return \Gitonomy\Git\Reference\Tag
   *   Tag.
   */
  protected function generateTag(Commit $commit): Tag {
    $tag = $this->prophesize(Tag::class);
    $tag->getCommitHash()->willReturn($commit->getHash());
    $tag->getCommit()->willReturn($commit);

    return $tag->reveal();
  }

  /**
   * Generates a reference bag.
   *
   * @param Tag[] $tags
   *   Tags.
   *
   * @return \Gitonomy\Git\ReferenceBag
   *   Reference Bag.
   */
  protected function generateReferenceBag(array $tags): ReferenceBag {
    $referenceBag = $this->prophesize(ReferenceBag::class);
    $referenceBag->getTags()->willReturn($tags);

    return $referenceBag->reveal();
  }

  /**
   * Generates log.
   *
   * @param Commit[] $commits
   *   Commits
   *
   * @return \Gitonomy\Git\Log
   *   Log.
   */
  protected function generateLog(array $commits): Log {
    $log = $this->prophesize(Log::class);
    $log->getCommits()->willReturn($commits);

    return $log->reveal();
  }

  /**
   * Generates repository.
   *
   * @param \Gitonomy\Git\Commit $commit
   *   Commit.
   * @param \Gitonomy\Git\Log $log
   *   Log.
   * @param \Gitonomy\Git\ReferenceBag $referenceBag
   *   Reference bag.
   *
   * @return \Gitonomy\Git\Repository
   *   Repository
   */
  protected function generateRepository(Commit $commit, Log $log, ReferenceBag $referenceBag): Repository {
    $repository = $this->prophesize(Repository::class);
    $repository->getHeadCommit()->willReturn($commit);
    $repository->getLog([$commit->getHash()])->willReturn($log);
    $repository->getReferences()->willReturn($referenceBag);
    $repository->getCommit($commit->getHash())->willReturn($commit);

    return $repository->reveal();
  }

}
