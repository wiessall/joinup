<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Step\When;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\AssertTrait;
use Drupal\joinup\Traits\EntityTrait;
use Drupal\meta_entity\Entity\MetaEntityInterface;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Behat step definitions for testing meta entities.
 */
class MetaEntityContext extends RawDrupalContext {

  use AssertTrait;
  use EntityTrait;

  /**
   * Translates the configurable listing paragraph of a topic meta entity.
   *
   * Topic bodies reference their paragraphs through a non-translatable field,
   * so the (shared) paragraph carries its own translations. This adds a
   * translation to the configurable listing paragraph and points the parent at
   * the resulting revision so the translation is rendered.
   *
   * @param string $langcode
   *   The language code of the translation to set, e.g. 'el'.
   * @param string $term
   *   The label of the topic taxonomy term the meta entity belongs to.
   * @param \Behat\Gherkin\Node\TableNode $fields
   *   The translatable fields to set, as "field"/"value" rows.
   */
  #[Given('the :langcode translation of the configurable listing in the :term topic has:')]
  public function translateConfigurableListing(string $langcode, string $term, TableNode $fields): void {
    $topic = $this->getEntityByLabel('taxonomy_term', $term, 'topic');
    $meta_entity = \Drupal::getContainer()->get('meta_entity.repository')->getMetaEntityForEntity($topic, 'topic');

    $delta = NULL;
    $paragraph = NULL;
    foreach ($meta_entity->get('topics_paragraph') as $index => $item) {
      if ($item->entity instanceof ParagraphInterface && $item->entity->bundle() === 'configurable_listing') {
        $delta = $index;
        $paragraph = $item->entity;
        break;
      }
    }
    if ($paragraph === NULL) {
      throw new \RuntimeException(sprintf('No configurable listing paragraph found in the "%s" topic.', $term));
    }

    $translation = $paragraph->hasTranslation($langcode) ? $paragraph->getTranslation($langcode) : $paragraph->addTranslation($langcode, $paragraph->toArray());
    foreach ($fields->getHash() as $row) {
      $translation->set($row['field'], $row['value']);
    }
    $translation->save();

    // The paragraph save created a new revision. Point the parent at it so the
    // translation is rendered, then save the parent.
    $meta_entity->get('topics_paragraph')->get($delta)->set('target_revision_id', $paragraph->getRevisionId());
    $meta_entity->save();
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
