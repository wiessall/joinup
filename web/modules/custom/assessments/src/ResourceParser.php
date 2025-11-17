<?php

declare(strict_types=1);

namespace Drupal\assessments;

use Drupal\Component\Uuid\Php;
use Drupal\assessments\Model\AssessmentModel;
use Drupal\assessments\Model\AssessmentModelInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @todo Add class description.
 */
final class ResourceParser implements ResourceParserInterface {

  /**
   * The list of models.
   *
   * @var \Drupal\assessments\Model\AssessmentModelInterface[]
   */
  protected array $models;

  /**
   * The assessment report model.
   *
   * @var \Drupal\assessments\Model\AssessmentModelInterface
   */
  protected AssessmentModelInterface $assessmentReport;

  /**
   * The entity chain.
   *
   * @var \Drupal\Core\Entity\EntityInterface[]
   */
  protected array $entities = [];

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'uuid')]
    protected Php $uuid,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function parseInbound(array $data, ?AssessmentModelInterface $parent = NULL, string $type = 'assessment'): void {
    // Initialize an assessment model.
    $model = AssessmentModel::createFromContext($data, $parent, $type);
    if ($type === 'assessment') {
      $this->assessmentReport = $model;
    }

    $this->models[] = $model;
    foreach ($model->getReferencedEntityProperties() as $key) {
      if (isset($data[$key])) {
        foreach ($this->entryAsList($data[$key]) as $subentry) {
          $this->parseInbound($subentry, $model, $key);
        }
      }
    }
  }

  public function generateEntityChain(AssessmentModelInterface $parent = NULL): array {
    // Start from the root assessment model when no parent is provided.
    if ($parent === NULL) {
      $parent = $this->assessmentReport;
    }

    $violations = [];

    // Recurse into child models first (post-order traversal).
    foreach ($this->models as $model) {
      if ($model->getParent() === $parent) {
        $violations = array_merge($violations, $this->generateEntityChain($model));
      }
    }

    // Create (but do not save) the entity for the current model.
    $storage = $this->entityTypeManager->getStorage($parent->getEntityTypeId());
    $entity = $storage->create($parent->getEntityValues());

    // Keep the created entity in the chain for potential later use.
    $this->entities[] = $entity;

    // Validate the entity and collect violations.
    $entity_errors = $entity->validate();
    if ($entity_errors->count() > 0) {
      foreach ($entity_errors as $error) {
        $violations[] = [
          'message' => $error->getMessage(),
        ];
      }
    }

    return $violations;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityValidationErrors(): array {
    $errors = [];
    foreach ($this->models as $model) {
      $entity = \Drupal::entityTypeManager()
        ->getStorage($model->getEntityTypeId())
        ->create($model->getEntityValues());

      $entity_errors = $entity->validate();
      if ($entity_errors->count() > 0) {
        foreach ($entity_errors as $error) {
          $errors[] = [
            'message' => $error->getMessage(),
          ];
        }
      }
    }
    return $errors;
  }

  /**
   * Returns an array from a single value.
   *
   * @param array|string|null $entry
   *   The value to be converted.
   *
   * @return array
   *   The converted value.
   */
  protected function entryAsList(array|string|null $entry): array {
    if ($entry === NULL) {
      return [];
    }
    return is_array($entry) && array_is_list($entry) ? $entry : [$entry];
  }

}
