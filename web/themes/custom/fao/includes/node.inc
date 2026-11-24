<?php

use Drupal\node\NodeInterface;
use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;
use Drupal\taxonomy\Entity\Term;
use Drupal\core\Url;
function fao_preprocess_node(&$variables) {

  $node = $variables['node'];

  if($node instanceof NodeInterface) {
    switch($node->bundle()) {

      case 'innovation':

        if(!$node->get('field_use_case_image')->isEmpty()) {
          $field_use_case_image = $node->get('field_use_case_image')->getValue();

          if (!empty($field_use_case_image[0]['target_id'])) {

            $term = Drupal::entityTypeManager()
              ->getStorage('taxonomy_term')
              ->load($field_use_case_image[0]['target_id']);


            if ($term instanceof Term) {
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
        break;

      case 'initiative':
      case 'digital_asset':

        if(!$node->get('field_image')->isEmpty()) {
          $field_image = $node->get('field_image')->getValue();

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
        break;
    }

    if($node->hasField('field_impact_sdgs')) {

      $sdgs = $node->get('field_impact_sdgs')->getValue();

      foreach($sdgs as $sdg) {
        if(!empty($sdg['target_id'])) {

          $term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($sdg['target_id']);

          if($term instanceof Term) {

            $url = Url::fromRoute('entity.taxonomy_term.canonical', ['taxonomy_term' => $term->id()])->toString();

            switch ($sdg['target_id']) {
              //SDG 1
              case 1158:
                $variables['sdgs'][] = [
                  'class' => 'sdg-1',
                  'url' => $url
                ];
                break;
              //SDG 2
              case 1159:
                $variables['sdgs'][] = [
                  'class' => 'sdg-2',
                  'url' => $url
                ];
                break;
              //SDG 3
              case 1160:
                $variables['sdgs'][] = [
                  'class' => 'sdg-3',
                  'url' => $url
                ];
                break;
              //SDG 4
              case 1161:
                $variables['sdgs'][] = [
                  'class' => 'sdg-4',
                  'url' => $url
                ];
                break;
              //SDG 5
              case 1162:
                $variables['sdgs'][] = [
                  'class' => 'sdg-5',
                  'url' => $url
                ];
                break;
              //SDG 6
              case 1163:
                $variables['sdgs'][] = [
                  'class' => 'sdg-6',
                  'url' => $url
                ];
                break;
              //SDG 7
              case 1164:
                $variables['sdgs'][] = [
                  'class' => 'sdg-7',
                  'url' => $url
                ];
                break;
              //SDG 8
              case 1165:
                $variables['sdgs'][] = [
                  'class' => 'sdg-8',
                  'url' => $url
                ];
                break;
              //SDG 9
              case 1166:
                $variables['sdgs'][] = [
                  'class' => 'sdg-9',
                  'url' => $url
                ];
                break;
              //SDG 10
              case 1167:
                $variables['sdgs'][] = [
                  'class' => 'sdg-10',
                  'url' => $url
                ];
                break;
              //SDG 11
              case 1168:
                $variables['sdgs'][] = [
                  'class' => 'sdg-11',
                  'url' => $url
                ];
                break;
              //SDG 12
              case 1169:
                $variables['sdgs'][] = [
                  'class' => 'sdg-12',
                  'url' => $url
                ];
                break;
              //SDG 13
              case 1170:
                $variables['sdgs'][] = [
                  'class' => 'sdg-13',
                  'url' => $url
                ];
                break;
              //SDG 14
              case 1171:
                $variables['sdgs'][] = [
                  'class' => 'sdg-14',
                  'url' => $url
                ];
                break;
              //SDG 15
              case 1172:
                $variables['sdgs'][] = [
                  'class' => 'sdg-15',
                  'url' => $url
                ];
                break;
              //SDG 16
              case 1173:
                $variables['sdgs'][] = [
                  'class' => 'sdg-16',
                  'url' => $url
                ];
                break;
              //SDG 17
              case 1174:
                $variables['sdgs'][] = [
                  'class' => 'sdg-17',
                  'url' => $url
                ];
                break;

            }


          }
        }
      }
    }
  }
}
