<?php

declare(strict_types = 1);

namespace Drupal\search_api_field\EventSubscriber;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\search_api_field\Event\AlterQueryPresetsDescriptionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Search API field event subscriber.
 */
class QueryPresetSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * Alters the description for the query preset.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
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

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      AlterQueryPresetsDescriptionEvent::class => ['onDescriptionAlter'],
    ];
  }

}
