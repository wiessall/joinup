<?php

declare(strict_types=1);

namespace Drupal\joinup_oss_catalogue\Drush\Commands;

use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\eu_oss_catalogue\Commands\EuOssCatalogueCommands;
use Drupal\eu_oss_catalogue\EuOssCatalogueInterface;
use Drupal\eu_oss_catalogue\EuOssCataloguePluginManagerInterface;
use Drupal\eu_oss_vitality_index\Commands\EuOssVitalityIndexCommands;
use Drupal\eu_oss_vitality_index\VitalityIndexGeneratorInterface;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * EU OSS Catalogue commands replacements.
 *
 * @todo Remove this class in ISAICP-9053.
 */
class JoinupOssCatalogueCommands extends DrushCommands {

  use AutowireTrait;

  public function __construct(
    protected readonly EuOssCatalogueInterface $euOssCatalogueService,
    #[Autowire(service: 'plugin.manager.eu_oss_catalogue.provider')]
    protected readonly EuOssCataloguePluginManagerInterface $providerPluginManager,
    protected readonly VitalityIndexGeneratorInterface $vitalityIndexGenerator,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct();
  }

  /**
   * Alters 'eu-oss:fetch' command.
   */
  #[CLI\Hook(type: HookManager::REPLACE_COMMAND_HOOK, target: 'eu-oss:fetch')]
  public function fetch(string $provider): string {
    if (joinup_oss_catalogue_is_enabled()) {
      $commands = new EuOssCatalogueCommands($this->euOssCatalogueService, $this->providerPluginManager);
      return $commands->fetch($provider);
    }
    return dt('EU OSS Catalogue functionality is disabled.');
  }

  /**
   * Alters 'eu-oss:generate-vitality-index' command.
   */
  #[CLI\Hook(type: HookManager::REPLACE_COMMAND_HOOK, target: 'eu-oss:generate-vitality-index')]
  public function generate(string $nid): string {
    if (joinup_oss_catalogue_is_enabled()) {
      $commands = new EuOssVitalityIndexCommands($this->vitalityIndexGenerator, $this->entityTypeManager);
      return $commands->generate($nid);
    }
    return dt('EU OSS Catalogue functionality is disabled.');
  }

}
