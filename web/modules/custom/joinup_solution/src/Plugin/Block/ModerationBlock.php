<?php

declare(strict_types=1);

namespace Drupal\joinup_solution\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block showing the group moderation value.
 */
#[Block(
  id: 'solution_group_moderation',
  admin_label: new TranslatableMarkup('Moderation'),
  category: new TranslatableMarkup('Solution'),
)]
class ModerationBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $pluginId,
    mixed $pluginDefinition,
    protected readonly RouteMatchInterface $routeMatch,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $node = $this->routeMatch->getParameter('node');
    if ($node instanceof NodeInterface && $node->bundle() === 'solution') {
      $solutionSettings = $this->entityTypeManager->getStorage('meta_entity')
        ->loadByProperties(['target__target_id' => $node->id(), 'type' => 'solution_settings']);
      $solutionSettings = array_shift($solutionSettings);
      if (!empty($solutionSettings)) {
        $groupModeration = $solutionSettings->get('group_moderation');
        $groupModerationValue = $groupModeration->value;
        $allowedValues = $groupModeration->getSettings();
        $label = $groupModerationValue ? $allowedValues['on_label'] : $allowedValues['off_label'];
        $build['group_moderation'] = [
          '#markup' => $this->t('<b>Only facilitators and authors can create content. </b><br> @value', ['@value' => $label]),
        ];
      }
    }

    return $build;
  }

}
