<?php

declare(strict_types=1);

namespace Drupal\joinup_tmgmt;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Keeps what was extracted from the entity extracted last.
 *
 * @see \Drupal\joinup_tmgmt\Tmgmt\JoinupContentEntitySource::extractTranslatableData()
 */
class ExtractedDataMemo {

  /**
   * The entity the value was derived from.
   */
  protected ?string $key = NULL;

  /**
   * The value derived from that entity.
   */
  protected ?array $value = NULL;

  /**
   * How often what is kept was invalidated.
   */
  protected int $generation = 0;

  public function __construct(
    protected readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Returns what was derived from an entity, deriving it once.
   *
   * Only pass an entity as it is stored: an entity carrying unwritten changes
   * is named by the same key as the stored one.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity the value is derived from.
   * @param callable $derive
   *   Derives the value from the entity, called only when it is not known yet.
   *
   * @return array
   *   The value.
   */
  public function remember(ContentEntityInterface $entity, callable $derive): array {
    $key = $this->getKey($entity);
    if ($key !== NULL && $key === $this->key) {
      return $this->value;
    }

    $generation = $this->generation;
    $value = $derive($entity);
    if ($key !== NULL && $generation === $this->generation) {
      $this->key = $key;
      $this->value = $value;
    }

    return $value;
  }

  /**
   * Forgets what was derived from an entity, before it is written.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to forget.
   */
  public function forget(ContentEntityInterface $entity): void {
    $this->generation++;

    if ($this->key === NULL) {
      return;
    }

    $prefix = $entity->getEntityTypeId() . ':' . $entity->id() . ':';
    if ($this->isEmbeddable($entity) || str_starts_with($this->key, $prefix)) {
      $this->key = NULL;
      $this->value = NULL;
    }
  }

  /**
   * Returns the key under which what is derived from an entity is kept.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return string|null
   *   The key, or NULL for an entity that has nothing stable to key on.
   */
  protected function getKey(ContentEntityInterface $entity): ?string {
    if ($entity->isNew() || $entity->id() === NULL || $entity->isNewRevision()) {
      return NULL;
    }

    return implode(':', [
      $entity->getEntityTypeId(),
      $entity->id(),
      (string) ($entity->getEntityType()->isRevisionable() ? $entity->getRevisionId() : ''),
      $entity->language()->getId(),
    ]);
  }

  /**
   * Checks whether an entity is extracted as part of another entity.
   *
   * Which entity a configured embedded reference points at is not resolved: as
   * long as any is configured, every entity is treated as embedded.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return bool
   *   Whether the entity can be part of the data of another entity.
   *
   * @see \Drupal\tmgmt_content\Plugin\tmgmt\Source\ContentEntitySource::getEmbeddableFields()
   */
  protected function isEmbeddable(ContentEntityInterface $entity): bool {
    return (bool) $entity->getEntityType()->get('entity_revision_parent_type_field')
      || (bool) $this->configFactory->get('tmgmt_content.settings')->get('embedded_fields');
  }

}
