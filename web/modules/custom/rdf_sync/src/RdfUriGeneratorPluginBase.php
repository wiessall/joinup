<?php

declare(strict_types = 1);

namespace Drupal\rdf_sync;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a base class for RDF URI generator plugins.
 */
abstract class RdfUriGeneratorPluginBase extends PluginBase implements RdfUriGeneratorPluginInterface, ContainerFactoryPluginInterface {

  /**
   * The entity for which the ID is being generated.
   */
  protected ContentEntityInterface $entity;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function setEntity(ContentEntityInterface $entity): RdfUriGeneratorPluginInterface {
    $this->entity = $entity;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntity(): ContentEntityInterface {
    return $this->entity;
  }

}
