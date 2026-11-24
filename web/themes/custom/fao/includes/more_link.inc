<?php

use Drupal\Core\Render\Markup;

/**
 *
 */
function fao_preprocess_container__more_link(&$variables) {
  $url  = $variables['element']['#url']->toString();
  $variables['children'] = Markup::create('<a href="'.$url.'" class="link-icon">'.$variables['element']['#title'].'<i class="bi bi-chevron-right"></i></a>');
}
