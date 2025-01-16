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
 *   entity_type = "node",
 * )
 */
class JoinupGroupValidator extends ArgumentValidatorPluginBase {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
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
    return $this->entityTypeManager->getStorage('node')->load((string) $arg) instanceof GroupInterface;
  }

}
