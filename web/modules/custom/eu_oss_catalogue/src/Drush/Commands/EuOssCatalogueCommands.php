<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Drush\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\CommandError;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drupal\eu_oss_catalogue\EuOssCatalogueInterface;
use Drupal\eu_oss_catalogue\EuOssCataloguePluginManagerInterface;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Drush commands.
 */
class EuOssCatalogueCommands extends DrushCommands {

  use AutowireTrait;

  public function __construct(
    protected readonly EuOssCatalogueInterface $euOssCatalogueService,
    #[Autowire(service: 'plugin.manager.eu_oss_catalogue.provider')]
    protected readonly EuOssCataloguePluginManagerInterface $providerPluginManager,
  ) {
    parent::__construct();
  }

  /**
   * Fetch OSS solutions from a provider.
   */
  #[CLI\Command(name: 'eu-oss:fetch')]
  #[CLI\Argument(name: 'provider', description: 'The provider plugin ID')]
  public function fetch(string $provider): ?string {
    if (!$this->euOssCatalogueService->isEnabled()) {
      $this->logger()->warning(dt('EU OSS Catalogue functionality is not enabled.'));
      return NULL;
    }

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
