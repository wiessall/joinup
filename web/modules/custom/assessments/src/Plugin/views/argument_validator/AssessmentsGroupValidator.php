<?php

declare(strict_types=1);

namespace Drupal\assessments\Plugin\views\argument_validator;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\assessments\AssessmentInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\views\Attribute\ViewsArgumentValidator;
use Drupal\views\Plugin\views\argument_validator\ArgumentValidatorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Validates that the argument is the assessments group.
 *
 * @ingroup views_argument_validate_plugins
 */
#[ViewsArgumentValidator(
  id: "assessments_group",
  title: new TranslatableMarkup("Assessments group"),
  entity_type: "node",
)]
class AssessmentsGroupValidator extends ArgumentValidatorPluginBase {

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
    if (!is_numeric($arg)) {
      return FALSE;
    }

    $group = $this->entityTypeManager->getStorage('node')->load($arg);
    return $group instanceof GroupInterface && $group->uuid() === AssessmentInterface::GROUP_UUID;
  }

}
