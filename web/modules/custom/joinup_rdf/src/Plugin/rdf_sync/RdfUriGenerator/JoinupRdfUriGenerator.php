<?php

declare(strict_types=1);

namespace Drupal\joinup_rdf\Plugin\rdf_sync\RdfUriGenerator;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\rdf_sync\RdfUriGeneratorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Generates RDF URIs within the namespace assigned by the publication office.
 *
 * @RdfUriGenerator(
 *   id = "joinup_po_namespace",
 *   name = @Translation("Joinup PO namespace (data.europa.eu/w21)"),
 * )
 */
class JoinupRdfUriGenerator extends RdfUriGeneratorPluginBase {

  /**
   * Constructs a new plugin instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid
   *   The UUID service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected UuidInterface $uuid) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('uuid'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function generate(): string {
    return "http://data.europa.eu/w21/{$this->uuid->generate()}";
  }

}
