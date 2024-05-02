<?php

declare(strict_types=1);

namespace Drupal\eu_oss_vitality_index;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Site\Settings;
use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;
use Gitonomy\Git\Admin;
use Gitonomy\Git\Repository;

/**
 * Vitality Index service.
 */
class VitalityIndexGenerator implements VitalityIndexGeneratorInterface {

  /**
   * Directory relative to 'private://'.
   */
  protected const GIT_CLONE_DIR = 'eu_oss_catalogue';

  /**
   * Constructs a new service instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The EU OSS Catalogue logger channel.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LoggerChannelInterface $logger,
  ) {}

  /**
   * Computes and stores the vitality index for one solution.
   *
   * @param \Drupal\eu_oss_catalogue\Entity\OssSolutionInterface $solution
   *   The OSS Solution entity.
   */
  public function processSolution(OssSolutionInterface $solution): void {
    if ($vitalityIndex = $this->getVitalityIndexPerDay($solution)) {
      // Order chronologically.
      $vitalityIndexEncoded = Json::encode(array_reverse($vitalityIndex));
      if ($vitalityIndexEncoded !== $solution->get('oss_vitality_index_range')->value) {
        $solution
          ->set('oss_vitality_index_range', $vitalityIndexEncoded)
          ->set('oss_vitality_index', array_sum($vitalityIndex) / count($vitalityIndex) / 100)
          ->save();
      }
    }
  }

  /**
   * Computes the vitality index as points per day.
   *
   * @param \Drupal\eu_oss_catalogue\Entity\OssSolutionInterface $solution
   *   The OSS Solution.
   *
   * @return int[]|null
   *   The vitality index as points per day or NULL if it cannot be computed.
   */
  protected function getVitalityIndexPerDay(OssSolutionInterface $solution): ?array {
    $url = $solution->getUrl();
    $url = str_ends_with($url, '.git') ? $url : "$url.git";
    $repoPath = $this->getLocalRepositoryPath($solution);

    $repository = is_dir($repoPath) ? $this->gitFetch($repoPath) : $this->gitClone($repoPath, $url);

    if (!$repository) {
      // The repository could not be cloned or fetches. Just ignore it for now
      // and continue the process. The error has been logged.
      return NULL;
    }

    return (new VitalityIndexProcessor($repository))->getPointsPerDay();
  }

  /**
   * Builds the repository local path.
   *
   * @param \Drupal\eu_oss_catalogue\Entity\OssSolutionInterface $solution
   *   The OSS Solution.
   *
   * @return string
   *   Path to cloned repository.
   */
  protected function getLocalRepositoryPath(OssSolutionInterface $solution): string {
    $url = parse_url($solution->getUrl());
    return implode(DIRECTORY_SEPARATOR, [
      Settings::get('file_private_path'),
      self::GIT_CLONE_DIR,
      $url['host'] . (isset($url['port']) ? "--{$url['port']}" : ''),
      trim($url['path'], DIRECTORY_SEPARATOR),
    ]);
  }

  /**
   * Clones a repository.
   *
   * @param string $localPath
   *   Destination local path.
   * @param string $remoteUrl
   *   The repository remote URL.
   *
   * @return \Gitonomy\Git\Repository|null
   *   The repository or NULL if clone failed.
   */
  protected function gitClone(string $localPath, string $remoteUrl): ?Repository {
    try {
      return Admin::cloneRepository($localPath, $remoteUrl, [
        '--mirror',
        '--no-checkout',
        '--filter=tree:0',
      ]);
    }
    catch (\Throwable $exception) {
      $this->logger->error('Cannot clone into @dir from @remote. Message: @message', [
        '@dir' => $localPath,
        '@remote' => $remoteUrl,
        '@message' => $exception->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * Fetches a Git repository.
   *
   * @param string $localPath
   *   Repository local path.
   *
   * @return \Gitonomy\Git\Repository|null
   *   The repository or NULL if fetch failed.
   */
  protected function gitFetch(string $localPath): ?Repository {
    try {
      $repository = new Repository($localPath);
      $repository->run('fetch');
      return $repository;
    }
    catch (\Throwable $exception) {
      $this->logger->error('Cannot fetch from remote for @dir. Message: @message', [
        '@dir' => $localPath,
        '@message' => $exception->getMessage(),
      ]);
      return NULL;
    }
  }

}
