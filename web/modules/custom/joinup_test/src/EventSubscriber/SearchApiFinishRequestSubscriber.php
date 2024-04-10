<?php

declare(strict_types=1);

namespace Drupal\joinup_test\EventSubscriber;

use Drupal\Core\State\StateInterface;
use Drupal\search_api\Utility\PostRequestIndexing;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Triggers search_api indexing on KernelEvents::FINISH_REQUEST.
 *
 * By default, search_api indexes items on KernelEvents::TERMINATE, after the
 * response was sent (in order not to block the response).
 * But this can cause next requests to retrieve old/outdated data from Solr if
 * the indexing or soft-commit in Solr didn't happen yet.
 * This ensures the next requests always get up-to-date data/responses from Solr
 * (e.g: when we are running behat).
 */
class SearchApiFinishRequestSubscriber implements EventSubscriberInterface {

  const STATE_KEY = 'joinup_test.index_on_finish_request';

  public function __construct(protected StateInterface $state, protected PostRequestIndexing $postRequestIndexing) {
  }

  /**
   * Indexes items on FINISH_REQUEST, if specified.
   */
  public function onKernelFinishRequest(): void {
    $is_active = $this->state->get(self::STATE_KEY, FALSE);
    if ($is_active) {
      $this->postRequestIndexing->destruct();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Fire this as late as possible.
    $events[KernelEvents::FINISH_REQUEST][] = ['onKernelFinishRequest', -100];
    return $events;
  }

}
