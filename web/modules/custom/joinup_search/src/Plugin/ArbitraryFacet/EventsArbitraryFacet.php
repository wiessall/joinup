<?php

declare(strict_types=1);

namespace Drupal\joinup_search\Plugin\ArbitraryFacet;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\facets\Utility\FacetsDateHandler;
use Drupal\search_api_arbitrary_facet\Attribute\ArbitraryFacet;
use Drupal\search_api_arbitrary_facet\Plugin\ArbitraryFacetBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides 'Upcoming events', 'My events' and 'Past events' facets.
 */
#[ArbitraryFacet(
  id: "events_arbitrary",
  label: new TranslatableMarkup("Upcoming/My/Past events"),
)]
class EventsArbitraryFacet extends ArbitraryFacetBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected readonly AccountProxyInterface $currentUser,
    protected readonly FacetsDateHandler $facetDateHandler,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user'),
      $container->get('facets.utility.date_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFacetDefinition(): array {
    $today_midnight = $this->facetDateHandler->isoDate((new DrupalDateTime('today'))->getTimestamp());
    $definition = [
      'upcoming_events' => [
        'field_name' => 'field_event_date_end',
        'field_condition' => $today_midnight,
        'field_operator' => '>=',
        'label' => $this->t('Upcoming events'),
      ],
    ];

    if (!$this->currentUser->isAnonymous()) {
      $definition['my_events'] = [
        'field_name' => 'entity_author',
        'field_condition' => $this->currentUser->id(),
        'label' => $this->t('My events'),
      ];
    }

    $definition += [
      'past_events' => [
        'field_name' => 'field_event_date_end',
        'field_condition' => $today_midnight,
        'field_operator' => '<=',
        'label' => $this->t('Past events'),
      ],
    ];

    return $definition;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return Cache::mergeContexts([
      'community_content_author:event',
    ], parent::getCacheContexts());
  }

}
