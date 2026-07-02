<?php

declare(strict_types=1);

namespace Drupal\topic\EventSubscriber;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\layout_paragraphs\LayoutParagraphsLayout;
use Drupal\search_api_field\Event\AlterQueryPresetsDescriptionEvent;
use Drupal\topic\Entity\TopicTermInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Joinup Topic event subscriber.
 */
class QueryPresetSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  public function __construct(
    protected readonly RouteMatchInterface $routeMatch,
  ) {}

  /**
   * Alters the description for the query preset.
   *
   * @param \Drupal\search_api_field\Event\AlterQueryPresetsDescriptionEvent $event
   *   Response event.
   */
  public function onDescriptionAlter(AlterQueryPresetsDescriptionEvent $event): void {
    $topic = $this->getTopicTerm();
    if (!$topic) {
      return;
    }
    $description = $event->getDescription();
    $description['topic_help'] = [
      '#type' => 'details',
      '#title' => $this->t('Extra examples'),
      '#open' => TRUE,
      'list' => [
        '#theme' => 'item_list',
        '#items' => [
          $this->t('Current topic: @label', ['@label' => $topic->label()]),
          $this->t('In order to only show entities related to this topic, use the following query: "topic|@label" (without the quotes) in a single line or use the "topic|@label,some-other-topic|IN" to include multiple topics.', ['@label' => $topic->label()]),
        ],
      ],
    ];
    $event->setDescription($description);
  }

  /**
   * Returns the topic term from the current route, or NULL if not applicable.
   *
   * @return \Drupal\topic\Entity\TopicTermInterface|null
   *   The topic term, or NULL if not on a layout paragraphs builder route for
   *   a topic entity.
   */
  protected function getTopicTerm(): ?TopicTermInterface {
    $allowed_routes = [
      'layout_paragraphs.builder.insert',
      'layout_paragraphs.builder.edit_item',
    ];
    if (!in_array($this->routeMatch->getRouteName(), $allowed_routes, TRUE)) {
      return NULL;
    }
    $layout = $this->routeMatch->getParameter('layout_paragraphs_layout');
    if (!$layout instanceof LayoutParagraphsLayout) {
      return NULL;
    }
    $entity = $layout->getEntity();
    return $entity instanceof TopicTermInterface ? $entity : NULL;
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
