<?php

/**
 * @file
 * Responsive image specific procedural functions.
 */

declare(strict_types=1);

use Drupal\joinup_community_content\Entity\CommunityContentInterface;

/**
 * Implements hook_preprocess_HOOK().
 */
function ventuno_preprocess_responsive_image_formatter(array &$variables): void {
  if ($variables['url']) {
    $options = $variables['url']->getOptions();
    if ($options['entity'] instanceof CommunityContentInterface) {
      $title = $options['entity']->getTitle();
    }
    else {
      $title = $options['entity']->label();
    }
    $variables['aria_label'] = 'Read more about \'' . $title . '\'.';
  }
}

/**
 * Implements hook_preprocess_responsive_image().
 */
function ventuno_preprocess_responsive_image(array &$variables): void {
  // Explicitly sets dimensions to image using responsive image styles to avoid
  // Cumulative Layout Shift. We use the 16:9 ratio as a premise: ratios are not
  // fixed, we allow users to upload images without restrictions. It's not
  // possible to match them exactly.
  switch ($variables['responsive_image_style_id']) {
    // Highlighted results on search page (3 first results).
    case 'teaser':
      $variables['img_element']['#attributes']['height'] = 290;
      $variables['img_element']['#attributes']['width'] = 516;
      break;

    // 'Highlighted content' section on homepage.
    case 'highlighted_content':
      $variables['img_element']['#attributes']['height'] = 392;
      $variables['img_element']['#attributes']['width'] = 696;
      break;

    // 'Logo' used on collection and solution headers.
    case 'logo':
      $variables['img_element']['#attributes']['height'] = 100;
      $variables['img_element']['#attributes']['width'] = 100;
      break;

    // 'Tile'.
    case 'tile':
      $variables['img_element']['#attributes']['height'] = 175;
      $variables['img_element']['#attributes']['width'] = 312;
      break;
  }
}
