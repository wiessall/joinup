<?php

declare(strict_types=1);

namespace Drupal\Tests\eu_oss_vitality_index\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\eu_oss_vitality_index\VitalityIndexProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests VitalityIndexProcessor.
 */
#[CoversClass(VitalityIndexProcessor::class)]
#[Group('eu_oss_catalogue')]
#[Group('eu_oss_vitality_index')]
class VitalityIndexProcessorTest extends UnitTestCase {

  use GitRepositoryTrait;

  /**
   * Provides test cases for getPointsPerDay() method.
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

    $expectedPoints = [46, 56, 46, 46, 52, 52, 58];
    $processor = new VitalityIndexProcessor($repository, 7);
    $this->assertEquals($expectedPoints, $processor->getPointsPerDay());
  }

  /**
   * Tests longevity range.
   */
  #[DataProvider('providerLongevityRange')]
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
   *
   * The summary of expected points follows a specific order: User community
   * range + Code activity range + Release history range + Longevity range.
   */
  public static function providerLongevityRange(): array {
    return [
      'range 100' => [100, 4 + 2 + 20 + 20],
      'range 400' => [400, 4 + 2 + 20 + 30],
      'range 900' => [900, 4 + 2 + 20 + 35],
    ];
  }

  /**
   * Tests code activity range.
   */
  #[DataProvider('providerCodeActivityRange')]
  public function testCodeActivityRange(int $numberOfCommits, int $expectedPoints): void {
    $commits = [];
    for ($x = 1; $x <= $numberOfCommits; $x++) {
      $commits[] = $this->generateCommit(0);
    }

    $referenceBag = $this->generateReferenceBag([]);
    $log = $this->generateLog($commits);
    $lastCommit = $commits[array_key_last($commits)];

    $repository = $this->generateRepository($lastCommit, $log, $referenceBag);

    $processor = new VitalityIndexProcessor($repository, 1);
    $this->assertEquals([$expectedPoints], $processor->getPointsPerDay());
  }

  /**
   * Data provider for testLongevityRange().
   *
   * The summary of expected points follows a specific order: User community
   * range + Code activity range + Release history range + Longevity range.
   */
  public static function providerCodeActivityRange(): array {
    return [
      'code activity 35' => [35, min(4 + 60 + 20 + 20, 100)],
      'code activity 18' => [18, 4 + 38 + 20 + 20],
      'code activity 9' => [9, 4 + 20 + 20 + 20],
      'code activity 4' => [4, 4 + 8 + 20 + 20],
      'code activity 1' => [1, 4 + 2 + 20 + 20],
    ];
  }

  /**
   * Tests user community range.
   */
  #[DataProvider('providerUserCommunityRange')]
  public function testUserCommunityRange(int $numberOfCommits, int $expectedPoints): void {
    $commits = [];
    for ($x = 1; $x <= $numberOfCommits; $x++) {
      $commits[] = $this->generateCommit(0, $x . '@dot.com');
    }

    $referenceBag = $this->generateReferenceBag([]);
    $log = $this->generateLog($commits);
    $lastCommit = $commits[array_key_last($commits)];

    $repository = $this->generateRepository($lastCommit, $log, $referenceBag);

    $processor = new VitalityIndexProcessor($repository, 1);
    $this->assertEquals([$expectedPoints], $processor->getPointsPerDay());
  }

  /**
   * Data provider for testUserCommunityRange().
   *
   * The summary of expected points follows a specific order: User community
   * range + Code activity range + Release history range + Longevity range.
   */
  public static function providerUserCommunityRange(): array {
    return [
      'user community range 24' => [24, min(32 + 38 + 20 + 20, 100)],
      'user community range 16' => [16, 24 + 32 + 20 + 20],
      'user community range 4' => [4, 12 + 8 + 20 + 20],
      'user community range 1' => [1, 4 + 2 + 20 + 20],
    ];
  }

  /**
   * Tests release history.
   */
  #[DataProvider('providerReleaseHistory')]
  public function testReleaseHistory(int $numberOfTags, int $expectedPoints): void {
    $commits = [];
    for ($x = 1; $x <= $numberOfTags; $x++) {
      $commits[] = $this->generateCommit(0);
    }
    $tags = [];
    foreach ($commits as $commit) {
      $tags[] = $this->generateTag($commit);
    }

    $referenceBag = $this->generateReferenceBag($tags);
    $log = $this->generateLog($commits);
    $lastCommit = $commits[array_key_last($commits)];

    $repository = $this->generateRepository($lastCommit, $log, $referenceBag);

    $processor = new VitalityIndexProcessor($repository, 1);
    $this->assertEquals([$expectedPoints], $processor->getPointsPerDay());
  }

  /**
   * Data provider for testReleaseHistory().
   *
   * The summary of expected points follows a specific order: User community
   * range + Code activity range + Release history range + Longevity range.
   */
  public static function providerReleaseHistory(): array {
    return [
      'release history 4' => [4, 4 + 8 + 50 + 20],
      'release history 2' => [2, 4 + 2 + 40 + 20],
      'release history 1' => [1, 4 + 2 + 30 + 20],
    ];
  }

}
