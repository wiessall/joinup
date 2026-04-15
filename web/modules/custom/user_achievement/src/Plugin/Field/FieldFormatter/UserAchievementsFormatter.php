<?php

declare(strict_types=1);

namespace Drupal\user_achievement\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders the user achievements field.
 */
#[FieldFormatter(
  id: 'user_achievements',
  label: new TranslatableMarkup('Achievement list'),
  field_types: ['entity_reference'],
)]
class UserAchievementsFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a UserAchievementsFormatter object.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition): bool {
    return $field_definition->getSetting('target_type') === 'user_achievement';
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    if ($items->isEmpty()) {
      return [];
    }

    $user = $items->getEntity();
    assert($user instanceof UserInterface);

    $achievements = $this->entityTypeManager
      ->getStorage('user_achievement')
      ->loadByProperties(['uid' => $user->id()]);

    if (empty($achievements)) {
      return [];
    }

    // Sort by bundle (achievement type) for a consistent display order across
    // all profiles.
    uasort($achievements, static fn($a, $b) => strcmp($a->bundle(), $b->bundle()));

    $view_builder = $this->entityTypeManager->getViewBuilder('user_achievement');

    $elements = [
      '#theme' => 'item_list',
      '#items' => [],
      '#attributes' => ['class' => ['user-achievements']],
    ];

    foreach ($achievements as $achievement) {
      $elements['#items'][] = $view_builder->view($achievement);
    }

    return [
      0 => $elements,
    ];
  }

}
