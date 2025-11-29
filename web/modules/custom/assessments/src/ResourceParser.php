<?php

declare(strict_types=1);

namespace Drupal\assessments;

use Drupal\Component\Uuid\Php;
use Drupal\assessments\Model\AssessmentModel;
use Drupal\assessments\Model\AssessmentModelInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Parses incoming JSON data into assessment models.
 *
 * The consumption process is as follows:
 * - Incoming data is coming as structured JSON.
 * - A recursive parser parses each object into a model class.
 * - The class holds all the child models and the entity values.
 * The validation process is as follows:
 * - First, the top model is accessed.
 * - While there are child models in the chain, the validator accesses them
 * first.
 * - Reaching the leaf models, the entity is validated.
 * - If the entity is new, it is checked for entity violations.
 * - If an ID was passed, it is checked whether it exists according to the
 * field settings handler (target_entity_id, target_bundle, etc.).
 * - If there are errors, they are stored in the model and returned in the
 * chain.
 * - Returning to the parent model, the corresponding field is marked with
 * errors if there are errors in the child models, or if the cardinality is
 * problematic.
 * - The models have a "$validated" flag that is set to TRUE after validation.
 * The validation flag does not mean that it passed validation, only that it
 * was processed.
 * Creating the entities happens if the root model is validated successfully.
 * The process is as follows:
 * - A recursive function creates the entities from the leafs to the root.
 * - If the model is a new entity, the entity is saved. The generated ID is
 * stored in the model.
 * - The ID is returned to be used as entity values.
 * - The generated
 *
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
  public function parseInbound(array $data, ?AssessmentModelInterface $parent = NULL, string $type = 'assessment', ?int $delta = NULL): void {
    // Initialize an assessment model.
    $model = AssessmentModel::createFromContext($data, $parent, $type, $delta);
    if ($type === 'assessment') {
      $this->assessmentReport = $model;
    }

    // First, check for the required properties.
    foreach ($model->getRequiredResourceProperties() as $key) {
      if (!isset($data[$key])) {
        $model->addError($key, 'Missing required property: ' . $key);
      }
    }

    // Then, get all values apart from the referenced entities.
    foreach ($model->getAllowedResourceProperties() as $key) {
      if (isset($data[$key]) && !in_array($key, $model->getReferencedEntityProperties())) {
        $model->addEntityValue($key, $data[$key] ?? NULL);
      }
    }

    $this->models[] = $model;
    foreach ($model->getReferencedEntityProperties() as $key) {
      if (isset($data[$key])) {
        foreach ($this->entryAsList($data[$key]) as $index => $subentry) {
          $this->parseInbound($subentry, $model, $key, $index);
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
  public function getEntityValidationErrors(?AssessmentModelInterface $model = NULL): array {
    static $errors = [];
    if ($model !== NULL) {
      $model = $this->assessmentReport;
    }

    foreach ($this->models as $model) {
      $entity = \Drupal::entityTypeManager()
        ->getStorage($model->getEntityTypeId())
        ->create($model->getEntityValues());

      $entity_errors = $entity->validate();
      if ($entity_errors->count() > 0) {
        foreach ($entity_errors as $error) {
          $errors[] = [
            'message' => 'Property path: ' . $error->getPropertyPath() . ', Error: ' . $error->getMessage(),
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
