<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Plugin\views\argument_validator;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\views\Plugin\views\argument_validator\ArgumentValidatorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Validates that the argument is a group.
 *
 * @ingroup views_argument_validate_plugins
 *
 * @ViewsArgumentValidator(
 *   id = "joinup_group",
 *   title = @Translation("Joinup group"),
 *   entity_type = "rdf_entity",
 * )
 */
class JoinupGroupValidator extends ArgumentValidatorPluginBase {

  /**
   * Constructs a new argument validator plugin instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager
  ) {
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
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validateArgument($arg): bool {
    return $this->entityTypeManager->getStorage('rdf_entity')->load((string) $arg) instanceof GroupInterface;
  }

}
