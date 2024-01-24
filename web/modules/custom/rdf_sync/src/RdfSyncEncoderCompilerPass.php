<?php

declare(strict_types = 1);

namespace Drupal\rdf_sync;

use Drupal\rdf_sync\Encoder\RdfSyncEncoder;
use EasyRdf\Format;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Creates an RDF encoder service for each format from rdf_sync container param.
 */
class RdfSyncEncoderCompilerPass implements CompilerPassInterface {

  /**
   * {@inheritdoc}
   */
  public function process(ContainerBuilder $container): void {
    $rdfSerializers = Format::getFormats();
    foreach ($container->getParameter('rdf_sync.format') as $format) {
      if (isset($rdfSerializers[$format])) {
        $encoderService = new Definition(RdfSyncEncoder::class);
        $encoderService->addTag('encoder', [
          'format' => $format,
          // Acts before other encoders for the same formats.
          'priority' => 10,
        ]);
        $container->addDefinitions(["rdf_sync.encoder.$format" => $encoderService]);
      }
    }
  }

}
