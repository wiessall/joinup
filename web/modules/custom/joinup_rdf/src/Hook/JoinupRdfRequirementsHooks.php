<?php

declare(strict_types=1);

namespace Drupal\joinup_rdf\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\rdf_sync\RdfSyncSynchronizer;
use Joinup\EnvironmentDetector;

/**
 * Requirements hook implementations for the Joinup RDF module.
 */
final class JoinupRdfRequirementsHooks {

  use StringTranslationTrait;

  /**
   * Number of URIs verified per SPARQL query.
   */
  private const int BATCH_SIZE = 500;

  public function __construct(
    protected readonly RdfSyncSynchronizer $synchronizer,
    protected readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Implements hook_runtime_requirements().
   */
  #[Hook('runtime_requirements')]
  public function runtimeRequirements(): array {
    // These checks query the live triplestore, which only exists on
    // production; elsewhere (local, CI, acceptance) the graph is expected to be
    // out of sync.
    if (!EnvironmentDetector::isProduction() || $this->synchronizer->isSynchronizationEnabled()) {
      return [];
    }

    return [
      'joinup_rdf_synchronization' => [
        'title' => $this->t('RDF synchronization'),
        'value' => $this->t('Disabled'),
        'description' => $this->t('Automatic synchronization to the SPARQL endpoint is turned off. Entity changes are not being exported to the triplestore. Re-enable it with <code>drush rdf_sync:enable</code>.'),
        'severity' => RequirementSeverity::Warning,
      ],
    ];
  }

}
