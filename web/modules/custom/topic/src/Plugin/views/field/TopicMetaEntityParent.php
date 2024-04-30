<?php

declare(strict_types=1);

namespace Drupal\topic\Plugin\views\field;

use Drupal\Core\Url;
use Drupal\topic\Entity\TopicMetaEntityInterface;
use Drupal\views\Plugin\views\field\EntityLink;
use Drupal\views\ResultRow;

/**
 * Field handler to present a topic meta entity link to parent.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("topic_meta_entity_parent_link")
 */
class TopicMetaEntityParent extends EntityLink {

  /**
   * Static cache for computed parent topic meta entities.
   *
   * @var \Drupal\topic\Entity\TopicMetaEntityInterface[]
   */
  protected array $cache = [];

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $row): array {
    if ($this->getParent($row)) {
      return parent::render($row);
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function getUrlInfo(ResultRow $row): ?Url {
    if ($parent = $this->getParent($row)) {
      return $parent->toUrl()->setAbsolute();
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  protected function renderLink(ResultRow $row): string {
    if ($parent = $this->getParent($row)) {
      $this->options['alter']['make_link'] = TRUE;
      $this->options['alter']['url'] = $this->getUrlInfo($row);
      return $parent->label();
    }
    return '';
  }

  /**
   * Returns the topic meta entity parent.
   *
   * @param \Drupal\views\ResultRow $row
   *   The view result row.
   *
   * @return \Drupal\topic\Entity\TopicMetaEntityInterface|null
   *   The topic meta entity parent.
   */
  protected function getParent(ResultRow $row): ?TopicMetaEntityInterface {
    $meta_entity = $this->getEntity($row);
    if (!$meta_entity instanceof TopicMetaEntityInterface) {
      throw new \Exception("The 'topic_meta_entity_parent_link' Views field plugin is available only for topic meta entities.");
    }
    $meta_entity_id = $meta_entity->id();
    if (!isset($this->cache[$meta_entity_id])) {
      $term = $meta_entity->getTargetEntity();
      $term_parent = $term->get('parent');
      // The term will have a reference to root as '0' if it is a root term.
      $this->cache[$meta_entity_id] = $term_parent->isEmpty() || $term_parent->target_id === '0' ? NULL : $term_parent->entity->topic->entity;
    }
    return $this->cache[$meta_entity_id];
  }

  /**
   * {@inheritdoc}
   */
  public function clickSort($order) {
    // Throw an exception to prevent the sort from being added in the page.
    throw new \RuntimeException('Feature not supported due to the target entity being stored outside SQL, making it impossible to join tables.');
  }

}
