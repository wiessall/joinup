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
      'RDF entity' => 'rdf_entity',
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
    $allowed_types = ['taxonomy_term', 'rdf_entity', 'node'];
    if (!in_array($related_entity_type, $allowed_types)) {
      throw new \Exception("The related entity type '$related_entity_type' is not supported. Only the following types are supported: " . implode(', ', $allowed_types));
    }

    /** @var \Drupal\rdf_entity\Entity\Rdf $entity */
    $entity = $this->getEntityByLabel($related_entity_type, $related_entity);

    return \Drupal::getContainer()->get('meta_entity.repository')->getMetaEntityForEntity($entity, $bundle);
  }

}
