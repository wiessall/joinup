<?php

declare(strict_types=1);

namespace Drupal\isa2_analytics\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\oe_webtools_analytics\AnalyticsEventInterface;
use Drupal\oe_webtools_analytics\Event\AnalyticsEvent;
use Drupal\og\OgContextInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Subscriber that acts on visitor analytics being collected for reporting.
 */
readonly class WebtoolsAnalyticsSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected RequestStack $requestStack,
    protected RouteMatchInterface $routeMatch,
    #[Autowire(service: 'og.context')]
    protected OgContextInterface $ogContext,
  ) {
  }

  /**
   * Sets the site section on the analytics event.
   *
   * If the current page belongs to a certain group (collection or solution)
   * then this will be reported to Webtools Analytics as a "site section". This
   * allows the visitor data to be analysed for specific collections and
   * solutions.
   *
   * @param \Drupal\oe_webtools_analytics\AnalyticsEventInterface $event
   *   Response event.
   */
  public function setSiteSection(AnalyticsEventInterface $event): void {
    // The site section varies by the active group.
    $event->addCacheContexts(['og_group_context']);

    // Set the group label as the site section if there is an active group.
    if ($group = $this->ogContext->getGroup()) {
      if ($group->bundle() === 'solution') {

        $collection = NULL;
        // Normally, the solution has the `collections` computed field that
        // holds the list of collections that are affiliates to the solution.
        // However, this method is fired during the preparation of the page
        // while the computation of the value takes place during the rendering
        // of the page.
        // @see \Drupal\Core\Render\MainContent\HtmlRenderer::renderResponse
        if ($collections = joinup_solution_get_collection_ids($group)) {
          // Only the first affiliated collection should be used to set the site
          // section. This is considered to be the "main" collection. Any other
          // collections are ignored since they would make the analytics results
          // more difficult to interpret.
          $collection_id = reset($collections);
          $collection = $this->entityTypeManager->getStorage('node')->load($collection_id);
        }
        $group = $collection;
      }
    }

    if (!empty($group)) {
      $event->setSiteSection($group->getUri());
      $event->addCacheableDependency($group);
    }
  }

  /**
   * Sets the webtools search data in the MATOMO json.
   *
   * @param \Drupal\oe_webtools_analytics\AnalyticsEventInterface $event
   *   Response event.
   */
  public function setSearchData(AnalyticsEventInterface $event): void {
    if ($this->routeMatch->getRouteName() !== 'view.search.page_1') {
      return;
    }

    $current_request = $this->requestStack->getCurrentRequest();
    if ($keys = $current_request?->query->get('keys')) {
      $search_data = $event->getSearch();
      // Resolve malformed UTF-8 characters, possibly incorrectly encoded.
      $search_data->setKeyword(mb_convert_encoding($keys, 'UTF-8', 'UTF-8'));
      if ($total_rows = $current_request->attributes->get('total_rows')) {
        $search_data->setCount($total_rows);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[AnalyticsEvent::NAME][] = ['setSiteSection'];
    $events[AnalyticsEvent::NAME][] = ['setSearchData'];

    return $events;
  }

}
