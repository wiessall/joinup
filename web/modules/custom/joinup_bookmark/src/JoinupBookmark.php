<?php

declare(strict_types=1);

namespace Drupal\joinup_bookmark;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Implementation of joinup_bookmark service.
 */
class JoinupBookmark implements JoinupBookmarkInterface {

  use StringTranslationTrait;

  /**
   * Constructs an instance of the joinup_bookmark service.
   *
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current logged-in user.
   */
  public function __construct(
    protected AccountInterface $currentUser,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function getBookmarkLink(): array {
    try {
      return $this->generateLink();
    }
    catch (RouteNotFoundException $e) {
      return [];
    }
  }

  /**
   * Generates a bookmark link.
   *
   * @return array
   *   Bookmark link.
   *
   * @throws \Symfony\Component\Routing\Exception\RouteNotFoundException
   */
  protected function generateLink(): array {
    $bookmarkLink = Url::fromRoute('view.bookmarks.page');

    $bookmark = [
      'id' => 'bookmark',
      'label' => [
        '#type' => 'pattern',
        '#id' => 'icon',
        '#fields' => ['name' => 'bookmark', 'size' => 's'],
      ],
      'link' => $bookmarkLink,
      'attributes' => [
        'title' => $this->t('Bookmark'),
      ],
    ];

    // Display modal for not logged in users.
    if ($this->currentUser->isAnonymous()) {
      $bookmark['link'] = Url::fromRoute(
        'joinup_modal.anonymous',
        ['type' => 'bookmarks'],
        [
          'query' => [
            'destination' => $bookmarkLink->toString(),
          ],
        ]);
      $bookmark['attributes']['class'] = ['use-ajax'];
      $bookmark['attributes']['data-dialog-type'] = 'modal';
      $bookmark['attributes']['data-dialog-options'] = Json::encode([
        'width' => 'auto',
        'height' => 'auto',
      ]);
    }

    return $bookmark;
  }

}
