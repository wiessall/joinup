<?php

declare(strict_types = 1);

namespace Drupal\rdf_sync\Plugin\rdf_sync\RdfUriGenerator;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\rdf_sync\RdfUriGeneratorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a fallback entity ID generator plugin.
 *
 * @RdfUriGenerator(
 *   id = "default",
 *   name = @Translation("Default URI generator"),
 * )
 */
class DefaultRdfUriGenerator extends RdfUriGeneratorPluginBase {

  /**
   * Constructs a PolicyDomainVocabulary plugin.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid
   *   The UUID service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected UuidInterface $uuid,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('uuid')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function generate(): string {
    global $base_url;
    return "$base_url/{$this->getEntity()->getEntityTypeId()}/{$this->getEntity()->bundle()}/{$this->uuid->generate()}";
  }

}
