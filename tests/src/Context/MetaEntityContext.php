<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Gherkin\Node\TableNode;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\EntityTrait;
use Drupal\meta_entity\Entity\MetaEntityInterface;
use function PHPUnit\Framework\assertArrayHasKey;

/**
 * Behat step definitions for testing meta entities.
 */
class MetaEntityContext extends RawDrupalContext {

  use EntityTrait;

  /**
   * Enables the given topic meta entities on the homepage.
   *
   * @Given /^the following topics are enabled in the homepage:$/
   */
  public function theFollowingTopicsAreEnabledInTheHomepage(TableNode $table): void {
    $topics = $table->getColumn(0);
    $repository = \Drupal::getContainer()->get('meta_entity.repository');
    foreach ($topics as $topic) {
      $topic = $this->getEntityByLabel('taxonomy_term', $topic);
      $meta_entity = $repository->getMetaEntityForEntity($topic, 'topic');
      $meta_entity->set('published', TRUE);
      $meta_entity->save();
    }

    // Load the homepage_topics entity subqueue.
    $entity_queue = \Drupal::entityTypeManager()->getStorage('entity_subqueue')->load('homepage_topics');
    $entity_queue->addItem($meta_entity)->save();
  }

  /**
   * Navigates to the canonical page display of a meta entity.
   *
   * @param string $type
   *   The type of the path to visit. One of 'canonical' or 'edit'.
   * @param string $bundle
   *   The bundle of the meta entity.
   * @param string $related_entity
   *   The title of the related entity.
   * @param string $related_entity_type
   *   The type of the related entity.
   *
   * @When I go to the :type form of the :bundle meta entity of the :related_entity :related_bundle :related_entity_type
   * @When I go to the :type path of the :bundle meta entity of the :related_entity :related_bundle :related_entity_type
   */
  public function visitMetaEntityCanonicalPath(string $type, string $bundle, string $related_entity, string $related_entity_type): void {
    $path_types = [
      'canonical' => 'canonical',
      'edit' => 'edit-form',
    ];
    assertArrayHasKey($type, $path_types, "The path type '$type' is not supported. One of 'canonical' or 'edit' is expected.");

    $entity_type_mapping = [
      'content' => 'node',
      'taxonomy term' => 'taxonomy_term',
    ];
    if (!in_array($related_entity_type, array_keys($entity_type_mapping))) {
      throw new \InvalidArgumentException("The related entity type '$related_entity_type' is not supported. One of 'content' or 'RDF entity' is expected.");
    }

    $meta_entity = $this->getMetaEntityForEntity($entity_type_mapping[$related_entity_type], $related_entity, $bundle);
    $this->visitPath($meta_entity->toUrl($path_types[$type])->toString());
  }

  /**
   * Returns the meta entity for a given entity.
   *
   * @param string $related_entity_type
   *   The type of the related entity.
   * @param string $related_entity
   *   The title of the related entity.
   * @param string $bundle
   *   The bundle of the meta entity.
   *
   * @return \Drupal\meta_entity\Entity\MetaEntityInterface
   *   The meta entity.
   */
  protected function getMetaEntityForEntity(string $related_entity_type, string $related_entity, string $bundle): MetaEntityInterface {
    $allowed_types = ['taxonomy_term', 'node'];
    if (!in_array($related_entity_type, $allowed_types)) {
      throw new \Exception("The related entity type '$related_entity_type' is not supported. Only the following types are supported: " . implode(', ', $allowed_types));
    }

    $entity = $this->getEntityByLabel($related_entity_type, $related_entity);

    return \Drupal::getContainer()->get('meta_entity.repository')->getMetaEntityForEntity($entity, $bundle);
  }

  /**
   * Sets the visit or download count for a given entity to a certain value.
   *
   * @param string $type
   *   The type of counter: 'download' or 'visit'.
   * @param string $target_entity_label
   *   The target entity label.
   * @param string $count
   *   The counter to be set.
   *
   * @throws \Exception
   *   When:
   *   - An invalid $type was specified.
   *   - The entity with $target_entity_label doesn't exist.
   *   - A related meta entity doesn't exist.
   *
   * @Given the :type count of :target_entity_label is :count
   */
  public function setMetaEntityFieldValueForEntity(string $type, string $target_entity_label, string $count): void {
    if (!in_array($type, ['download', 'visit'])) {
      throw new \Exception("Type should be 'download' or 'visit' but '$type' was given.");
    }

    $field_name = "{$type}_count";
    /** @var \Drupal\Core\Entity\ContentEntityInterface $target_entity */
    $target_entity = $this->getEntityByLabel('node', $target_entity_label);

    /** @var \Drupal\meta_entity\Entity\MetaEntityInterface $meta_entity */
    $meta_entity = $target_entity->{$field_name}->entity;
    if (!$meta_entity) {
      throw new \Exception("The '$target_entity_label' doesn't have a related meta entity.");
    }

    $meta_entity->set('count', (int) $count)->save();
  }

}
