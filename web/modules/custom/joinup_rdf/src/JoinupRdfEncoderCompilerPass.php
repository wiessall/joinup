<?php

declare(strict_types=1);

namespace Drupal\joinup_rdf;

use Drupal\joinup_rdf\Encoder\JoinupRdfSyncEncoder;
use Drupal\rdf_sync\Model\RdfSyncFormat;
use EasyRdf\Format;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Adds temporary encoders.
 *
 * We need to support both type of encoders, provided by sparql_entity_storage
 * and rdf_sync modules.
 *
 * @todo Remove this hack in ISAICP-8376.
 * @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-8376
 * @see \Drupal\joinup_rdf\Encoder\JoinupRdfSyncEncoder
 */
class JoinupRdfEncoderCompilerPass implements CompilerPassInterface {

  /**
   * {@inheritdoc}
   */
  public function process(ContainerBuilder $container): void {
    $rdfSerializers = Format::getFormats();
    foreach (RdfSyncFormat::cases() as $format) {
      if (isset($rdfSerializers[$format->value])) {
        $encoderService = new Definition(JoinupRdfSyncEncoder::class);
        $encoderService->addTag('encoder', [
          'format' => $format->value,
          // These encoders will act before "rdf_sync.encoder.$format->value"
          // encoders, which have 10 as priority.
          // @see \Drupal\rdf_sync\RdfSyncEncoderCompilerPass::process()
          'priority' => 15,
        ]);
        $container->addDefinitions(["joinup_rdf.encoder.$format->value" => $encoderService]);
      }
    }
  }

}
