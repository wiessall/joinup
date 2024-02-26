<?php

declare(strict_types=1);

namespace Drupal\joinup_rdf;

use Drupal\joinup_rdf\Encoder\JoinupRdfSyncEncoder;
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
    foreach ($container->getParameter('rdf_sync.format') as $format) {
      if (isset($rdfSerializers[$format])) {
        $encoderService = new Definition(JoinupRdfSyncEncoder::class);
        $encoderService->addTag('encoder', [
          'format' => $format,
          // These encoders will act before "rdf_sync.encoder.$format" encoders,
          // which have 10 as priority.
          // @see \Drupal\rdf_sync\RdfSyncEncoderCompilerPass::process()
          'priority' => 15,
        ]);
        $container->addDefinitions(["joinup_rdf.encoder.$format" => $encoderService]);
      }
    }
  }

}
