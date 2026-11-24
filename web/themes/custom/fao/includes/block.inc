<?php

/**
 * Implements hook_preprocess_BLOCK_ID
 * Add class dropdown-item to language block and remove link if match with current language
 * @param $variables
 */
function fao_preprocess_links__language_block(&$variables) {
  $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
  foreach ($variables['links'] as $key => &$link){
    $link['link']['#options']['attributes']['class'][] = 'dropdown-item';
    if($link['link']['#options']['language']->getId() === $language) {
      unset($variables['links'][$key]);
    }
  }
}

