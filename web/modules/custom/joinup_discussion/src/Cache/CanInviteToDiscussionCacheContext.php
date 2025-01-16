<?php

declare(strict_types=1);

namespace Drupal\joinup_discussion\Cache;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\CacheContextInterface;
use Drupal\Core\Cache\Context\UserCacheContextBase;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;

/**
 * Defines the "can invite to discussion" cache context service.
 *
 * Cache context ID: 'can_invite_to_discussion'.
 */
class CanInviteToDiscussionCacheContext extends UserCacheContextBase implements CacheContextInterface {

  public function __construct(
    AccountInterface $user,
    protected readonly RouteMatchInterface $routeMatch,
  ) {
    parent::__construct($user);
  }

  /**
   * {@inheritdoc}
   */
  public static function getLabel() {
    return t('Can invite to discussion');
  }

  /**
   * {@inheritdoc}
   */
  public function getContext() {
    if ($this->routeMatch->getRouteName() === 'entity.node.canonical') {
      /** @var \Drupal\node\NodeInterface $discussion */
      if ($discussion = $this->routeMatch->getParameter('node')) {
        if ($discussion->bundle() === 'discussion') {
          $url = Url::fromRoute('joinup_discussion.invite_form', [
            'node' => $discussion->id(),
          ]);
          if ($url->access($this->user)) {
            return 'yes';
          }
        }
      }
    }
    return 'no';
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata() {
    return new CacheableMetadata();
  }

}
