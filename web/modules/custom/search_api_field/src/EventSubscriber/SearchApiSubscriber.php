<?php

declare(strict_types=1);

namespace Drupal\search_api_field\EventSubscriber;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\facets\FacetManager\DefaultFacetManager;
use Drupal\search_api\Event\QueryPreExecuteEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Drupal\search_api_field\Event\AlterQueryPresetsDescriptionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Performs subscribed alterations for the Search API field module.
 */
class SearchApiSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The Facet Manager.
   *
   * @var \Drupal\facets\FacetManager\DefaultFacetManager
   */
  protected DefaultFacetManager $facetManager;

  /**
   * Constructs SearchApiSubscriber.
   *
   * @param \Drupal\facets\FacetManager\DefaultFacetManager $facet_manager
   *   The facet manager service.
   */
  public function __construct(DefaultFacetManager $facet_manager) {
    $this->facetManager = $facet_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      SearchApiEvents::QUERY_PRE_EXECUTE => 'queryAlter',
      AlterQueryPresetsDescriptionEvent::class => ['onDescriptionAlter'],
    ];
  }

  /**
   * Adds the search_api_field facets to the facet query.
   *
   * @param \Drupal\search_api\Event\QueryPreExecuteEvent $event
   *   The query alter event.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  public function queryAlter(QueryPreExecuteEvent $event): void {
    $query = $event->getQuery();
    if ($query->getIndex()
      ->getServerInstance()
      ->supportsFeature('search_api_facets')) {
      $facet_source = 'search_api_field:' . $query->getSearchId();
      $this->facetManager->alterQuery($query, $facet_source);
    }
  }

  /**
   * Alters the description for the query preset.
   *
   * @param \Drupal\search_api_field\Event\AlterQueryPresetsDescriptionEvent $event
   *   Response event.
   */
  public function onDescriptionAlter(AlterQueryPresetsDescriptionEvent $event): void {
    $description = $event->getDescription();
    $index = $event->getIndex();
    $available_fields = $index->getFields(TRUE);
    $field_names = array_keys($available_fields);

    $description['fields'] = [
      '#type' => 'details',
      '#title' => $this->t('Available fields'),
      '#open' => FALSE,
      'list' => [
        '#type' => 'markup',
        '#markup' => implode(', ', $field_names),
      ],
    ];

    $event->setDescription($description);
  }

}
