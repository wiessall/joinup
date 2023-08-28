<?php

declare(strict_types = 1);

namespace Drupal\joinup_oss_catalogue\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\link\Plugin\Field\FieldFormatter\LinkFormatter;
use Symfony\Component\Mime\MimeTypeGuesserInterface;

/**
 * Temporary link-to-image formatter.
 *
 * @FieldFormatter(
 *   id = "tmp_link_to_image",
 *   label = @Translation("URL to Image"),
 *   field_types = {
 *     "link",
 *   }
 * )
 */
class TemporaryLinkToImageFormatter extends LinkFormatter {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    $fileTypeGuesser = \Drupal::service('file.mime_type.guesser');
    assert($fileTypeGuesser instanceof MimeTypeGuesserInterface);

    foreach ($items as $delta => $item) {
      if (
        !$item->isEmpty() &&
        ($url = $this->buildUrl($item)->toString()) &&
        str_starts_with($fileTypeGuesser->guessMimeType($url), 'image/')
      ) {
        $elements[$delta] = [
          '#theme' => 'image',
          '#uri' => $url,
          '#alt' => $items->getEntity()->label(),
          '#width' => 120,
        ];
      }
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    return [];
  }

}
