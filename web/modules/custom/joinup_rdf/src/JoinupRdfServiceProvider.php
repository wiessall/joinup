<?php

declare(strict_types=1);

namespace Drupal\joinup_rdf;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;

/**
 * Allows to temporarily use a custom encoder class.
 *
 * The RdfSyncEncoder encoders are acting for the same formats as the
 * SparqlEncoder. Workaround by temporarily using a custom class that handles
 * both.
 *
 * @todo Remove this hack in ISAICP-8376.
 * @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-8376
 * @see \Drupal\joinup_rdf\Encoder\JoinupRdfSyncEncoder
 */
class JoinupRdfServiceProvider implements ServiceProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    $container->addCompilerPass(new JoinupRdfEncoderCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 10);
  }

}
