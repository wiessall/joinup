<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Step\When;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\AssertTrait;
use Drupal\joinup\Traits\EntityTrait;
use Drupal\meta_entity\Entity\MetaEntityInterface;

/**
 * Behat step definitions for testing meta entities.
 */
class MetaEntityContext extends RawDrupalContext {

  use AssertTrait;
  use EntityTrait;

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
   */
  #[When('I go to the :type form of the :bundle meta entity of the :related_entity :related_bundle :related_entity_type')]
  #[When('I go to the :type path of the :bundle meta entity of the :related_entity :related_bundle :related_entity_type')]
  public function visitMetaEntityCanonicalPath(string $type, string $bundle, string $related_entity, string $related_entity_type): void {
    $path_types = [
      'canonical' => 'canonical',
      'edit' => 'edit-form',
    ];
    $this->assertCondition(array_key_exists($type, $path_types), "The path type '$type' is not supported. One of 'canonical' or 'edit' is expected.");

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

}
