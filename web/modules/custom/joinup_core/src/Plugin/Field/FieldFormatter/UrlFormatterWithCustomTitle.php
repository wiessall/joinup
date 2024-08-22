<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\link\Plugin\Field\FieldFormatter\LinkFormatter;

/**
 * Plugin implementation of the 'link' formatter.
 *
 * @FieldFormatter(
 *   id = "joinup_core_link_custom_title",
 *   label = @Translation("Link with Custom Title"),
 *   field_types = {
 *     "link"
 *   }
 * )
 */
class UrlFormatterWithCustomTitle extends LinkFormatter {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'link_title' => '',
    ] + parent::defaultSettings();
  }

  /**
   * Returns a form to configure settings for the formatter.
   *
   * @param array $form
   *   The form where the settings form is being included in.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The current state of the form.
   *
   * @return array
   *   The form elements for the formatter settings.
   */
  public function settingsForm(array $form, FormStateInterface $formState): array {
    $elements = parent::settingsForm($form, $formState);

    $elements['link_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Custom title'),
      '#field_suffix' => $this->t('characters'),
      '#default_value' => $this->getSetting('link_title'),
      '#description' => $this->t('Leave blank to allow unlimited link text lengths.'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = parent::settingsSummary();
    if ($label = $this->getSetting('link_title')) {
      $summary[] = $this->t('Custom label is: %label', [
        '%label' => $label,
      ]);
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = parent::viewElements($items, $langcode);
    if (empty($label = $this->getSetting('link_title'))) {
      return $elements;
    }

    foreach ($elements as $delta => $renderable) {
      if (!isset($renderable['#title'])) {
        continue;
      }
      $elements[$delta]['#title'] = $label;
    }

    return $elements;
  }

}
