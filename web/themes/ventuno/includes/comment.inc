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
  /** @var \Drupal\comment\CommentInterface $comment */
  $comment = $variables['comment'];
  /** @var \Drupal\user\Entity\User $user */
  $user = $variables['user_picture']['#user'];
  $photo_target = $user->get('field_user_photo')->getValue();

  $username = [
    '#theme' => 'username',
    '#account' => $user,
  ];

  if ($comment->get('anonymize')->value) {
    $variables['author'] = ['#markup' => t('Anonymous user')];
    $variables['user_picture'] = [
      '#theme' => 'image',
      '#uri' => ImageStyle::load('profile_icon')->buildUri('public://user_icon.png'),
      '#alt' => t('Anonymous user'),
    ];
    // Check if the user has the 'administer comments' permission.
    if (\Drupal::currentUser()->hasPermission('administer comments')) {
      // Add the user profile link in parentheses for moderators.
      $user_profile_link = \Drupal::service('renderer')->render($username);
      $variables['author']['#markup'] .= ' (' . $user_profile_link . ')';
    }
  }
  else {
    $variables['author'] = $username;
    if ($photo_target) {
      $photo_target = $photo_target[0]['target_id'];
      /** @var \Drupal\file\Entity\File $file */
      if ($file = File::load($photo_target)) {
        $uri = $file->getFileUri();
        $variables['user_picture'] = [
          '#theme' => 'image',
          '#uri' => ImageStyle::load('profile_icon')->buildUrl($uri),
          '#alt' => $user->getDisplayName(),
        ];
      }
    }
  }

  $cache_metadata = (new CacheableMetadata())->addCacheableDependency($user);
  $cache_metadata->applyTo($variables['author']);

  $cache_metadata
    ->addCacheTags($user->getEntityType()->getListCacheTags())
    ->applyTo($variables['user_picture']);

  if (!$comment->in_preview) {
    $variables['permalink_raw'] = $comment->permalink();
  }
}
