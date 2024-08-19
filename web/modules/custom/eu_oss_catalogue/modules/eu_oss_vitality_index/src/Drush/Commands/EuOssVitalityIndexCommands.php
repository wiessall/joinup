<?php

declare(strict_types=1);

namespace Drupal\eu_oss_vitality_index\Drush\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;
use Drupal\eu_oss_catalogue\EuOssCatalogueInterface;
use Drupal\eu_oss_vitality_index\VitalityIndexGeneratorInterface;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;

/**
 * Drush commands.
 */
class EuOssVitalityIndexCommands extends DrushCommands {

  use AutowireTrait;

  public function __construct(
    protected readonly VitalityIndexGeneratorInterface $vitalityIndexGenerator,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly EuOssCatalogueInterface $euOssCatalogueService,
  ) {
    parent::__construct();
  }

  /**
   * Generates vitality index for the selected solution id.
   */
  #[CLI\Command(name: 'eu-oss:generate-vitality-index')]
  #[CLI\Argument(name: 'nid', description: 'The solution node ID')]
  public function generate(string $nid): ?string {
    if (!$this->euOssCatalogueService->isEnabled()) {
      $this->logger()->warning(dt('EU OSS Catalogue functionality is not enabled.'));
      return NULL;
    }

    $solution = $this->getSolution($nid);
    $this->vitalityIndexGenerator->processSolution($solution);

    return dt('Generation of vitality index for @url was processed.', [
      '@url' => $solution->toUrl()->setAbsolute()->toString(),
    ]);
  }

  /**
   * Gets solution.
   *
   * @param string $nid
   *   Solution node id.
   */
  protected function getSolution(string $nid): ?OssSolutionInterface {
    $nodeStorage = $this->entityTypeManager->getStorage('node');
    $solution = $nodeStorage->load($nid);

    return $solution instanceof OssSolutionInterface ? $solution : NULL;
  }

  /**
   * Validates the argument of eu-oss:generate-vitality-index.
   */
  #[CLI\Hook(type: HookManager::ARGUMENT_VALIDATOR, target: 'eu-oss:generate-vitality-index')]
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
