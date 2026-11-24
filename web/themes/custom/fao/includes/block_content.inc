<?php
use Drupal\views\ViewExecutable;
use Drupal\block_content\Entity\BlockContent;
use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;


/**
 * Implements hook_theme_suggestions_TYPE_alter
 *
 * Add suggestion to block content adding bundle
 */
function fao_theme_suggestions_block_alter(array &$suggestions, array $variables)
{
  $elements = $variables['elements'];
  $entity = null;

  if(isset($elements['content']['#block_content'])) {
    $entity = $elements['content']['#block_content'];
  }

  if ($entity instanceof BlockContent) {
    $suggestions[] = 'block__block_content__' . $entity->bundle();
  }

}

/**
 * Ensures inline content blocks know the current interface language.
 */
function fao_preprocess_block__block_content__type__inline_content(array &$variables): void {
  $variables['current_language'] = \Drupal::languageManager()->getCurrentLanguage()->getId();
}



