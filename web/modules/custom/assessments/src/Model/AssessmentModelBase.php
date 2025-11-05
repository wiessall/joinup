<?php

declare(strict_types=1);

namespace Drupal\assessments\Model;

/**
 * Defines the assessment model base class.
 */
abstract class AssessmentModelBase implements AssessmentModelInterface{

  /**
   * Raw payload data for this model (structured object or UUID string wrapped upstream).
   */
  public function __construct(protected array $data) {}

  /**
   * If this model references an existing entity by UUID, store it here.
   */
  protected ?string $existingUuid = NULL;

  /**
   * Internal created entity ID after save.
   */
  protected ?string $createdId = NULL;

  /**
   * Returns the bundle of the assessment_content entity.
   *
   * @return string
   *   The bundle machine name.
   */
  abstract protected function bundle(): string;

  /**
   * Extracts the entity values from the data.
   *
   * @return array
   *   The entity values.
   */
  abstract protected function getEntityValues(): array;

  /**
   * Loads an existing entity by UUID if possible.
   */
  protected function loadExistingByUuid(?string $uuid): ?\Drupal\Core\Entity\EntityInterface {
    if (!$uuid) {
      return NULL;
    }
    $storage = \Drupal::entityTypeManager()->getStorage('assessment_content');
    $result = $storage->loadByProperties(['uuid' => $uuid]);
    if ($result) {
      return reset($result) ?: NULL;
    }
    return NULL;
  }

  /**
   * Returns the saved entity id (if any).
   */
  public function getSavedId(): ?string {
    return $this->createdId;
  }

  /**
   * {@inheritdoc}
   */
  public function validate(): array {
    // If this represents an existing entity by UUID, skip creation validation.
    if (!empty($this->data['_uuid'])) {
      $entity = $this->loadExistingByUuid($this->data['_uuid']);
      if ($entity) {
        return [];
      }
    }

    $entity = \Drupal::entityTypeManager()->getStorage('assessment_content')->create([
      'type' => $this->bundle(),
    ] + $this->getEntityValues());
    return $entity->validate();
  }

  /**
   * {@inheritdoc}
   */
  public function save(): void {
    // If payload references an existing entity via _uuid, try loading it and return.
    if (!empty($this->data['_uuid'])) {
      $existing = $this->loadExistingByUuid($this->data['_uuid']);
      if ($existing) {
        $this->createdId = (string) $existing->id();
        return;
      }
    }

    $values = ['type' => $this->bundle(), 'created' => REQUEST_TIME, 'uid' => \Drupal::currentUser()->id()] + $this->getEntityValues();
    $storage = \Drupal::entityTypeManager()->getStorage('assessment_content');
    $entity = $storage->create($values);
    $entity->save();
    $this->createdId = (string) $entity->id();
  }

}
