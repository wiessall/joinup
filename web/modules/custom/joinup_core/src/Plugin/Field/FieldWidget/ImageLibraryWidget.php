<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\image_library_widget\Plugin\Field\FieldWidget\ImageLibraryWidget as OriginalImageLibraryWidget;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * Extends the original image library widget and add SVG support.
 */
class ImageLibraryWidget extends OriginalImageLibraryWidget {

  /**
   * {@inheritdoc}
   */
  public function errorElement(array $element, ConstraintViolationInterface $error, array $form, FormStateInterface $form_state): array|false {
    $property_path_array = explode('.', $error->getPropertyPath());
    if (!isset($property_path_array[1])) {
      return $element;
    }
    $element = parent::errorElement($element, $error, $form, $form_state);
    return is_array($element) ? $element : FALSE;
  }

  /**
   * {@inheritdoc}
   *
   * Copied from \Drupal\svg_image\Plugin\Field\FieldWidget\SvgImageWidget.
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $field_settings = $this->getFieldSettings();

    $extensions = $field_settings['file_extensions'];
    $supported_extensions = $this->imageFactory->getSupportedExtensions();
    $supported_extensions[] = 'svg';

    // If using custom extension validation, ensure that the extensions are
    // supported by the current image toolkit. Otherwise, validate against all
    // toolkit supported extensions.
    $extensions = !empty($extensions) ? array_intersect(explode(' ', $extensions), $supported_extensions) : $supported_extensions;

    // Remove default image validation. Otherwise, we get an error on upload.
    unset($element['#upload_validators']['FileIsImage']);

    $element['#upload_validators']['FileExtension']['extensions'] = implode(' ', $extensions);

    return $element;
  }

  /**
   * {@inheritdoc}
   *
   * Copied from \Drupal\svg_image\Plugin\Field\FieldWidget\SvgImageWidget.
   */
  public static function process($element, FormStateInterface $form_state, $form) {
    $element = parent::process($element, $form_state, $form);

    // Add the image preview.
    if (!empty($element['#files']) && $element['#preview_image_style']) {
      $file = reset($element['#files']);

      // Override default image preview if SVG file.
      if (svg_image_is_file_svg($file)) {
        $variables = svg_image_get_image_file_dimensions($file);

        $variables['uri'] = $file->getFileUri();

        $element['preview'] = [
          '#weight' => -10,
          '#theme' => 'image',
          '#width' => $variables['width'],
          '#height' => $variables['height'],
          '#uri' => $variables['uri'],
        ];
      }
    }

    return $element;
  }

}
