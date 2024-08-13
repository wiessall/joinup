<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\CommandError;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drupal\eu_oss_catalogue\EuOssCatalogueInterface;
use Drupal\eu_oss_catalogue\EuOssCataloguePluginManagerInterface;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * Drush commands.
 */
class EuOssCatalogueCommands extends DrushCommands {

  /**
   * Constructs a new Drush commands class instance.
   *
   * @param \Drupal\eu_oss_catalogue\EuOssCatalogueInterface $euOssCatalogueService
   *   The EU OSS Catalogue service.
   * @param \Drupal\eu_oss_catalogue\EuOssCataloguePluginManagerInterface $providerPluginManager
   *   The EU OSS Catalogue provider plugin manager service.
   */
  public function __construct(
    protected EuOssCatalogueInterface $euOssCatalogueService,
    protected EuOssCataloguePluginManagerInterface $providerPluginManager,
  ) {
    parent::__construct();
  }

  /**
   * Fetch OSS solutions from a provider.
   */
  #[CLI\Command(name: 'eu-oss:fetch')]
  #[CLI\Argument(name: 'provider', description: 'The provider plugin ID')]
  public function fetch(string $provider): string {
    if ($fetched = $this->euOssCatalogueService->fetch($provider)) {
      return dt('@count OSS solutions fetched from @provider. The solutions are queued to be processed later', [
        '@count' => $fetched,
        '@provider' => $this->providerPluginManager->getDefinition($provider)['label'],
      ]);
    }
    return dt('No OSS solutions found from @provider', [
      '@provider' => $this->providerPluginManager->getDefinition($provider)['label'],
    ]);
  }

  /**
   * Validates the argument of eu-oss:fetch.
   */
  #[CLI\Hook(type: HookManager::ARGUMENT_VALIDATOR, selector: 'eu-oss:fetch')]
  public function validateProviderPluginId(CommandData $commandData): ?CommandError {
    $pluginId = $commandData->input()->getArgument('provider');
    if (!$this->providerPluginManager->hasDefinition($pluginId)) {
      return new CommandError(dt("The '@plugin' EU OSS Catalogue provider doesn't exist", [
        '@plugin' => $pluginId,
      ]));
    }
    return NULL;
  }

}
