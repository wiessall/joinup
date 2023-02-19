<?php

namespace Drupal\topic\EventSubscriber;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\search_api_field\Event\AlterQueryPresetsDescriptionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Joinup Topic event subscriber.
 */
class QueryPresetSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Constructs a QueryPresetSubscriber object.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   */
  public function __construct(RouteMatchInterface $route_match) {
    $this->routeMatch = $route_match;
  }

  /**
   * Alters the description for the query preset.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   Response event.
   */
  public function onDescriptionAlter(AlterQueryPresetsDescriptionEvent $event): void {
    if ($this->routeMatch->getRouteName() !== 'entity.meta_entity.edit_form') {
      return;
    }
    /** @var \Drupal\meta_entity\Entity\MetaEntityInterface $meta_entity */
    $meta_entity = $this->routeMatch->getParameter('meta_entity');
    $topic = $meta_entity->getTargetEntity();
    $description = $event->getDescription();

    $description['topic_help'] = [
      '#type' => 'details',
      '#title' => $this->t('Extra examples'),
      '#open' => TRUE,
      'list' => [
        '#theme' => 'item_list',
        '#items' => [
          $this->t('Current topic ID: @id', ['@id' => $topic->id()]),
          $this->t('In order to only show entities related to this topic, use the following query: "topic|@id" (without the quotes) in a single line or use the "topic|@id, http://some-other-topic|IN" to include multiple topics.', ['@id' => $topic->id()]),
        ],
      ],
    ];

    $event->setDescription($description);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      AlterQueryPresetsDescriptionEvent::class => ['onDescriptionAlter'],
    ];
  }

}
