<?php

declare(strict_types = 1);

namespace Drupal\joinup_core\Plugin\Deriver;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Deriver class for FormatterWithFallbackField.
 */
class AllFieldTypeFormatterDeriver extends DeriverBase implements ContainerDeriverInterface {

  /**
   * Constructs a new AllFieldTypeFormatterDeriver instance.
   *
   * @param \Drupal\Core\Field\FieldTypePluginManagerInterface $fieldTypePluginManager
   *   The field type plugin manager.
   */
  public function __construct(
    protected FieldTypePluginManagerInterface $fieldTypePluginManager
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('plugin.manager.field.field_type')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    // We only create one single derivative, but it will be marked as being
    // applicable to every available field type.
    return [
      [
        'field_types' => array_keys($this->fieldTypePluginManager->getDefinitions()),
      ] + $base_plugin_definition,
    ];
  }

}
