<?php

/**
 * @file
 * Comment specific procedural functions.
 */

declare(strict_types=1);

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;

/**
 * Implements hook_preprocess_comment().
 */
function ventuno_preprocess_comment(array &$variables): void {
  /** @var \Drupal\user\Entity\User $user */
  $user = $variables['user_picture']['#user'];
  $photo_target = $user->get('field_user_photo')->getValue();

  $variables['author'] = [
    '#theme' => 'username',
    '#account' => $user,
  ];

  $cache_metadata = (new CacheableMetadata())->addCacheableDependency($user);
  $cache_metadata->applyTo($variables['author']);

  if ($photo_target) {
    $photo_target = $photo_target[0]['target_id'];
    /** @var \Drupal\file\Entity\File $file */
    if ($file = File::load($photo_target)) {
      $uri = $file->getFileUri();
      $url = ImageStyle::load('profile_icon_big')->buildUrl($uri);
      $variables['profile_icon'] = ['#markup' => $url];
      $cache_metadata
        ->addCacheTags($user->getEntityType()->getListCacheTags())
        ->applyTo($variables['profile_icon']);
    }
  }

  /** @var \Drupal\comment\CommentInterface $comment */
  $comment = $variables['comment'];
  if (!$comment->in_preview) {
    $variables['permalink_raw'] = $comment->permalink();
  }
}
