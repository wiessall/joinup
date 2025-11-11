<?php

declare(strict_types=1);

namespace Drupal\assessments;

use Drupal\Component\Uuid\Php;
use Drupal\assessments\Model\AssessmentModel;
use Drupal\assessments\Model\AssessmentModelInterface;
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

  public function __construct(
    #[Autowire(service: 'uuid')]
    protected Php $uuid,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function parseInbound(array $data, ?AssessmentModelInterface $parent = NULL, string $type = 'assessment'): void {
    // Initialize an assessment model.
    $model = AssessmentModel::createFromContext($data, $parent, $type);
    $this->models[] = $model;
    foreach ($model->getReferencedEntityProperties() as $key) {
      if (isset($data[$key])) {
        foreach ($this->entryAsList($data[$key]) as $subentry) {
          $this->parseInbound($subentry, $model, $key);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityValidationErrors(): array {
    $errors = [];
    foreach ($this->models as $model) {
      $entity = \Drupal::entityTypeManager()
        ->getStorage('assessment')
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
