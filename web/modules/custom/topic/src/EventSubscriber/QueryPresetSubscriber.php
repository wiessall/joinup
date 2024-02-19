<?php

declare(strict_types=1);

namespace Drupal\topic\EventSubscriber;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\search_api_field\Event\AlterQueryPresetsDescriptionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Joinup Topic event subscriber.
 */
class QueryPresetSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * Constructs a QueryPresetSubscriber object.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   */
  public function __construct(protected RouteMatchInterface $routeMatch) {}

  /**
   * Alters the description for the query preset.
   *
   * @param \Drupal\search_api_field\Event\AlterQueryPresetsDescriptionEvent $event
   *   Response event.
   */
  public function onDescriptionAlter(AlterQueryPresetsDescriptionEvent $event): void {
    if ($this->routeMatch->getRouteName() !== 'entity.meta_entity.edit_form') {
      return;
    }
    /** @var \Drupal\meta_entity\Entity\MetaEntityInterface $metaEntity */
    $metaEntity = $this->routeMatch->getParameter('meta_entity');
    $topic = $metaEntity->getTargetEntity();
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
