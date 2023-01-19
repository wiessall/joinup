<?php

declare(strict_types = 1);

namespace Drupal\joinup\Context;

use Behat\Gherkin\Node\TableNode;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\EntityTrait;
use Drupal\meta_entity\Entity\MetaEntityInterface;

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
  public function theFollowingTopicsAreEnabledInTheHomepage(TableNode $table) {
    $topics = $table->getColumn(0);
    $repository = \Drupal::getContainer()->get('meta_entity.repository');
    foreach ($topics as $topic) {
      $topic = $this->getEntityByLabel('taxonomy_term', $topic);
      $meta_entity = $repository->getMetaEntityForEntity($topic, 'topic');
      $meta_entity->set('published', TRUE);
      $meta_entity->save();
    }
  }

  /**
   * Navigates to the canonical page display of a meta entity.
   *
   * @param string $bundle
   *   The bundle of the meta entity.
   * @param string $related_entity
   *   The title of the related entity.
   * @param string $related_entity_type
   *   The type of the related entity.
   *
   * @When I go to the :bundle meta entity of the :related_entity :related_bundle :related_entity_type
   */
  public function visitMetaEntityCanonicalPath(string $bundle, string $related_entity, string $related_entity_type): void {
    $meta_entity = $this->getMetaEntityForEntity($related_entity_type, $related_entity, $bundle);
    $this->visitPath($meta_entity->toUrl()->toString());
  }

  /**
   * Navigates to the edit page of a meta entity.
   *
   * @param string $bundle
   *   The bundle of the meta entity.
   * @param string $related_entity
   *   The title of the related entity.
   * @param string $related_entity_type
   *   The type of the related entity.
   *
   * @When I go to the :bundle meta entity of the :related_entity :related_bundle :related_entity_type
   */
  public function visitMetaEntityEditPage(string $bundle, string $related_entity, string $related_entity_type): void {
    $meta_entity = $this->getMetaEntityForEntity($related_entity_type, $related_entity, $bundle);
    $this->visitPath($meta_entity->toUrl('edit-form')->toString());
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
