<?php

declare(strict_types=1);

namespace Drupal\joinup_badge\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\joinup_badge\Entity\BadgeAwardingInterface;
use Drupal\joinup_badge\Plugin\Field\FieldType\BadgeFieldItemList;

/**
 * Badges computed field formatter.
 */
#[FieldFormatter(
  id: 'joinup_badge',
  label: new TranslatableMarkup('Joinup badge'),
  field_types: ['entity_reference'],
)]
class BadgeFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition): bool {
    return parent::isApplicable($field_definition)
      && $field_definition->getTargetEntityTypeId() === 'user'
      && $field_definition->getName() === 'badges';
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    foreach ($items as $delta => $item) {
      if ($item->target_id !== BadgeFieldItemList::NOT_AWARDED) {
        $badge = $item->entity;
        if (!$badge instanceof BadgeAwardingInterface) {
          continue;
        }

        $elements[$delta] = [
          '#theme' => 'joinup_badge',
          '#badge_type' => $badge->bundle(),
          '#label' => $badge->getLabel(),
          '#description' => $badge->getDescription(),
          '#icon' => $badge->getIcon(),
        ];
      }
    }
    return $elements;
  }

}
