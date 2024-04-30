<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue;

use Gitonomy\Git\Commit;
use Gitonomy\Git\Reference\Tag;
use Gitonomy\Git\Repository;

/**
 * Vitality index processor.
 *
 * This class takes a Git repository object and computes the vitality index
 * value based on an algorithm similar to Developers Italia. The algorithm is
 * described here "Vitality Index computing (developers.italia approach)" page.
 *
 * @see https://citnet.tech.ec.europa.eu/CITnet/confluence/pages/viewpage.action?pageId=1272453903
 */
class VitalityIndexProcessor {

  /**
   * Longevity range.
   */
  private const LONGEVITY_RANGE = [
    730 => 35,
    365 => 30,
    0 => 20,
  ];

  /**
   * Code activity range.
   */
  private const CODE_ACTIVITY_RANGE = [
    35 => 60,
    30 => 50,
    25 => 44,
    18 => 38,
    15 => 32,
    12 => 26,
    9 => 20,
    6 => 14,
    4 => 8,
    0 => 2,
  ];

  /**
   * Release history range.
   */
  private const RELEASE_HISTORY_RANGE = [
    4 => 50,
    2 => 40,
    1 => 30,
    0 => 20,
  ];

  /**
   * User community range.
   */
  private const USER_COMMUNITY_RANGE = [
    28 => 36,
    24 => 32,
    20 => 28,
    16 => 24,
    12 => 20,
    8 => 16,
    4 => 12,
    2 => 8,
    0 => 4,
  ];

  /**
   * The current time.
   *
   * @var \DateTimeImmutable
   */
  private readonly \DateTimeImmutable $now;

  /**
   * Static cache of all Git commits.
   *
   * @var \Gitonomy\Git\Commit[]
   */
  private array $commits;

  /**
   * Static cache of all Git tags.
   *
   * @var \Gitonomy\Git\Reference\Tag[]
   */
  private array $tags;

  /**
   * Constructs a new processor instance.
   *
   * @param \Gitonomy\Git\Repository $repository
   *   The Git repository.
   * @param int $days
   *   (optional) The number of days to compute the vitality index.
   */
  public function __construct(
    protected readonly Repository $repository,
    protected readonly int $days = 60,
  ) {
    $this->now = new \DateTimeImmutable();
  }

  /**
   * Returns the vitality index as a list of points in the last days.
   *
   * @return int[]
   *   The vitality index.
   */
  public function getPointsPerDay(): array {
    $vitalityIndexPerDay = [];
    $commitsBeforeLastDays = $this->getCommitsBeforeLastDays();
    $commitsPerDay = $this->getCommitsPerDayInLastDays();
    $tagsPerDay = $this->getTagCommitsPerDayInLastDays();
    for ($i = 0; $i < $this->days; $i++) {
      $communityPoints = $this->getPointsFromRange(self::USER_COMMUNITY_RANGE, $this->getNumberOfAuthors($commitsBeforeLastDays[$i]));
      $codeActivityPoints = $this->getPointsFromRange(self::CODE_ACTIVITY_RANGE, $this->getCodeActivity($commitsPerDay[$i]));
      $releaseHistoryPoints = $this->getPointsFromRange(self::RELEASE_HISTORY_RANGE, count($tagsPerDay[$i]));
      $longevityPoints = $this->getPointsFromRange(self::LONGEVITY_RANGE, $this->getDaysElapsedSinceOldestCommit());

      $vitalityIndexPerDay[$i] = $communityPoints + $codeActivityPoints + $releaseHistoryPoints + $longevityPoints;
      // Limit to 100.
      $vitalityIndexPerDay[$i] = min($vitalityIndexPerDay[$i], 100);
    }
    return $vitalityIndexPerDay;
  }

  /**
   * Returns a list commits created before each day from last $this->days days.
   *
   * @return \Gitonomy\Git\Commit[][]
   *   List of commits grouped per day.
   */
  private function getCommitsBeforeLastDays(): array {
    $commits = [];
    for ($i = 0; $i < $this->days; $i++) {
      $date = $this->now->sub(new \DateInterval("P{$i}D"));
      $commits[$i] = array_filter(
        $this->getCommits(),
        function (Commit $commit) use ($date): bool {
          return $commit->getCommitterDate() < $date;
        }
      );
    }
    return $commits;
  }

  /**
   * Returns a list commits created in each day from last $this->days days.
   *
   * @param \Gitonomy\Git\Commit[]|null $fromCommits
   *   (optional) If passed, narrows the list where to search.
   *
   * @return \Gitonomy\Git\Commit[][]
   *   List of commits grouped per day.
   */
  private function getCommitsPerDayInLastDays(?array $fromCommits = NULL): array {
    $fromCommits ??= $this->getCommits();

    $commits = [];
    for ($i = 0; $i < $this->days; $i++) {
      $date = $this->now->sub(new \DateInterval("P{$i}D"));
      $commits[$i] = array_filter($fromCommits, function (Commit $commit) use ($date): bool {
        return $commit->getCommitterDate()->format('Y-m-d') === $date->format('Y-m-d');
      });
    }
    return $commits;
  }

  /**
   * Returns the commits corresponding to each Git tag.
   *
   * @return \Gitonomy\Git\Commit[]
   *   List of commits.
   */
  private function getTagCommits(): array {
    return array_filter(
      array_map(
        fn(Tag $tag): ?Commit => $this->getCommits()[$tag->getCommitHash()] ?? NULL,
        $this->getTags(),
      )
    );
  }

  /**
   * Returns a list commits associated with tags for each day in the last days.
   *
   * @return \Gitonomy\Git\Commit[][]
   *   List of tag commits grouped per day.
   */
  private function getTagCommitsPerDayInLastDays(): array {
    return $this->getCommitsPerDayInLastDays($this->getTagCommits());
  }

  /**
   * Returns the date of the oldest commit.
   *
   * @return \DateTime
   *   The date of the oldest commit.
   */
  private function getOldestCommitDate(): \DateTime {
    $reversed = array_reverse(array_keys($this->getCommits()));
    $hash = reset($reversed);
    $date = $this->repository->getCommit($hash);

    // Git was invented in 2005. If some repo starts before, consider that the
    // oldest date.
    return max($date, new \DateTime('2005-01-01T00:00:00'));
  }

  /**
   * Returns the number of days elapsed since the oldest commit.
   *
   * @return int
   *   Days elapsed since the oldest commit
   */
  protected function getDaysElapsedSinceOldestCommit(): int {
    return $this->now->diff($this->getOldestCommitDate())->days;
  }

  /**
   * Returns the number of authors given a list of commits.
   *
   * @param \Gitonomy\Git\Commit[] $commits
   *   List of commits.
   *
   * @return int
   *   Number of authors.
   */
  private function getNumberOfAuthors(array $commits): int {
    $authors = [];
    foreach ($commits as $commit) {
      $authors[$commit->getAuthorEmail()][] = TRUE;
    }
    return count($authors);
  }

  /**
   * Computes a value that accounts for number of commits and merges.
   *
   * @param \Gitonomy\Git\Commit[] $commits
   *   List of commits.
   *
   * @return int
   *   Number of commits plus contained number of merges.
   */
  private function getCodeActivity(array $commits): int {
    $mergeCount = 0;
    foreach ($commits as $commit) {
      if (count($commit->getParentHashes()) > 1) {
        $mergeCount++;
      }
    }
    return count($commits) + $mergeCount;
  }

  /**
   * Calculates the number of points from a range given a specific input.
   *
   * @param array $range
   *   The range array.
   * @param int $value
   *   The input value.
   *
   * @return int
   *   The number of points.
   *
   * @throws \InvalidArgumentException
   *   When the input value cannot be resolved.
   */
  private function getPointsFromRange(array $range, int $value): int {
    foreach ($range as $threshold => $points) {
      if ($value >= $threshold) {
        return $points;
      }
    }
    throw new \InvalidArgumentException("The input value ($value) cannot be resolved with the passed range");
  }

  /**
   * Returns all commits.
   *
   * @return \Gitonomy\Git\Commit[]
   *   List of commits keyed by commit hash.
   */
  private function getCommits(): array {
    if (!isset($this->commits)) {
      foreach ($this->repository->getLog([$this->repository->getHeadCommit()->getHash()])->getCommits() as $commit) {
        $this->commits[$commit->getHash()] = $commit;
      }
    }
    return $this->commits;
  }

  /**
   * Returns the list of all tags.
   *
   * @return \Gitonomy\Git\Reference\Tag[]
   *   List of Git tags.
   */
  private function getTags(): array {
    if (!isset($this->tags)) {
      $this->tags = $this->repository->getReferences()->getTags();
    }
    return $this->tags;
  }

}
