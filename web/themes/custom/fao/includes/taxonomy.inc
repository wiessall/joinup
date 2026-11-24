<?php

use Drupal\taxonomy\Entity\Term;
use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;


function fao_theme_suggestions_taxonomy_term_alter(array &$suggestions, array $variables) {

  /** @var \Drupal\taxonomy\TermInterface $term */
  $term = $variables['elements']['#taxonomy_term'];


  // \Drupal::logger('fao')->debug('Term: @term, Bundle: @bundle, View mode: @vm', [
  //   '@term' => $term->label(),
  //   '@bundle' => $term->bundle(),
  //   '@vm' => $variables['elements']['#view_mode'],
  // ]);

  //Valeria: add all-bundles view mode suggestion:

  $suggestions[] = 'taxonomy_term__' . strtr($variables['elements']['#view_mode'], '.', '_');
  $suggestions[] = 'taxonomy_term__' . $term->bundle() . '__' . strtr($variables['elements']['#view_mode'], '.', '_');

return $suggestions;
}

function fao_preprocess_taxonomy_term(&$variables) {

  $term = $variables['term'];

  if($term instanceof Term) {

    if($term->hasField('field_image')) {

      $field_image = $term->get('field_image')->getValue();

      if (!empty($field_image[0]['target_id'])) {
        $media = Drupal::entityTypeManager()
          ->getStorage('media')
          ->load($field_image[0]['target_id']);

        $field_media_image = $media->get('field_media_image')->getValue();

        if (!empty($field_media_image[0]['target_id'])) {
          $file = Drupal::entityTypeManager()
            ->getStorage('file')
            ->load($field_media_image[0]['target_id']);

          if ($file instanceof File) {
            $image_uri = $file->getFileUri();
            $style = ImageStyle::load('wide');
            if (!empty($style)) {
              $variables['image'] = [
                'url' => $style->buildUrl($image_uri),
              ];
            }
          }
        }
      }
    }

  }
}
