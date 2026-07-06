<?php

declare(strict_types=1);

namespace Drupal\joinup_rdf\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\rdf_sync\RdfSyncSynchronizer;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Requirements hook implementations for the Joinup RDF module.
 */
final class JoinupRdfRequirementsHooks {

  use StringTranslationTrait;

  public function __construct(
    #[Autowire(service: 'rdf_sync.synchronizer')]
    protected readonly RdfSyncSynchronizer $synchronizer,
    protected readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Implements hook_runtime_requirements().
   */
  #[Hook('runtime_requirements')]
  public function runtimeRequirements(): array {
    if ($this->synchronizer->isSynchronizationEnabled()) {
      return [];
    }

    return [
      'joinup_rdf_synchronization' => [
        'title' => $this->t('RDF synchronization'),
        'value' => $this->t('Disabled'),
        'description' => $this->t('Automatic synchronization to the SPARQL endpoint is turned off. Entity changes are not being exported to the triplestore. Re-enable it in <a href="@url">the settings</a>.', [
          '@url' => Url::fromRoute('rdf_sync.synchronization')->toString(),
        ]),
        'severity' => RequirementSeverity::Warning,
      ],
    ];
  }

}
