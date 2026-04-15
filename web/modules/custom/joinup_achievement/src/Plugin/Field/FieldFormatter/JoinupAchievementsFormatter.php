<?php

declare(strict_types=1);

namespace Drupal\joinup_achievement\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\joinup_achievement\AchievementFlagInterface;

/**
 * Renders the user achievement flags field.
 */
#[FieldFormatter(
  id: 'joinup_achievements',
  label: new TranslatableMarkup('Achievement flag list'),
  field_types: ['entity_reference'],
)]
class JoinupAchievementsFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition): bool {
    return $field_definition->getName() === 'joinup_achievements';
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    if ($items->isEmpty()) {
      return [];
    }

    $flaggings = $items->referencedEntities();
    $flaggings = array_filter($flaggings, static fn($f) => $f instanceof AchievementFlagInterface);

    if (empty($flaggings)) {
      return [];
    }

    $elements = [
      '#theme' => 'item_list',
      '#items' => [],
      '#attributes' => ['class' => ['user-achievements']],
      '#attached' => [
        'library' => ['ventuno/user-achievement'],
      ],
    ];

    foreach ($flaggings as $flagging) {
      assert($flagging instanceof AchievementFlagInterface);
      $elements['#items'][] = [
        '#theme' => 'joinup_achievement',
        '#icon_id' => $flagging->getIcon(),
        '#label' => $flagging->getLabel(),
        '#description' => $flagging->getDescription(),
      ];
    }

    return [0 => $elements];
  }

}
