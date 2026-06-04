<?php

declare(strict_types=1);

namespace Drupal\joinup_discussion\Plugin\views\cache;

use Drupal\views\Plugin\views\cache\Tag;

/**
 * Cache plugin that invalidates the discussions_sitewide view on new replies.
 */
class CommentReplyTag extends Tag {

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $tags = parent::getCacheTags();
    if ($this->view->id() === 'discussions_sitewide') {
      $tags[] = 'comment_list:reply';
    }
    return $tags;
  }

}
