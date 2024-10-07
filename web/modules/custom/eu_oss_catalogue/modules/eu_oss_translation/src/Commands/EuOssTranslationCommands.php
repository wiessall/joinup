<?php

declare(strict_types=1);

namespace Drupal\eu_oss_translation\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;
use Drupal\eu_oss_translation\JobBuilderInterface;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * Drush commands.
 */
class EuOssTranslationCommands extends DrushCommands {

  public function __construct(
    protected JobBuilderInterface $jobBuilder,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct();
  }

  /**
   * Generates translation job.
   */
  #[CLI\Command(name: 'eu-oss:generate-translation-job')]
  #[CLI\Argument(name: 'nid', description: 'The solution node ID')]
  public function generate(string $nid): string {
    $solution = $this->getSolution($nid);
    $this->jobBuilder->create($solution);

    return dt('Generation of translation job for @url was processed.', [
      '@url' => $solution->toUrl()->setAbsolute()->toString(),
    ]);
  }

  /**
   * Gets solution.
   *
   * @param string $nid
   *   Solution node id.
   *
   * @return \Drupal\eu_oss_catalogue\Entity\OssSolutionInterface|null
   *   OSS solution.
   */
  protected function getSolution(string $nid): ?OssSolutionInterface {
    $nodeStorage = $this->entityTypeManager->getStorage('node');
    $solution = $nodeStorage->load($nid);

    return $solution instanceof OssSolutionInterface ? $solution : NULL;
  }

  /**
   * Validates the argument of eu-oss:generate-vitality-index.
   */
  #[CLI\Hook(type: HookManager::ARGUMENT_VALIDATOR, target: 'eu-oss:generate-translation-job')]
  public function validateSolutionId(CommandData $commandData): void {
    $nid = $commandData->input()->getArgument('nid');

    $solution = $this->getSolution($nid);
    if (!$solution) {
      throw new \Exception(dt("The solution with node id '@nid' doesn't exist", [
        '@nid' => $nid,
      ]));
    }
  }

}
