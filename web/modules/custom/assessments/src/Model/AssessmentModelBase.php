<?php

declare(strict_types=1);

namespace Drupal\assessments\Model;

/**
 * Defines the assessment model base class.
 */
abstract class AssessmentModelBase implements AssessmentModelInterface {

  /**
   * The generated or existing entity ID.
   *
   * @var string
   */
  protected string $entityId;

  /**
   * Indicates whether the entity is new or not.
   *
   * @var bool
   */
  protected bool $isNew = TRUE;

  /**
   * The parent model.
   *
   * @var \Drupal\assessments\Model\AssessmentModelInterface|null
   */
  protected ?AssessmentModelInterface $parent;

  /**
   * A parsed array of entity values.
   *
   * @var array
   */
  protected array $entityValues = [];

  /**
   * An array of validation errors.
   *
   * @var array
   */
  protected array $errors = [];

  public function __construct(array $data = [], ?AssessmentModelInterface $parent = NULL) {
    if (in_array('id', $this->getAllowedResourceProperties()) && !empty($data['id'])) {
      $this->isNew = FALSE;
    }
    else {
      $this->isNew = TRUE;
    }

    foreach ($data as $key => $value) {
      if (in_array($key, $this->getAllowedResourceProperties())) {
        $this->entityValues[$key] = $value;
      }
      elseif (!in_array($key, $this->getReferencedEntityProperties())) {
        $this->errors[] = "Invalid property: {$key}";
      }
    }

    $this->parent = $parent;
  }

  /**
   * Returns the ID of the entity, either new or existing.
   *
   * @return string
   *   The entity ID.
   */
  public function getEntityId(): string {
    return $this->entityId;
  }

  /**
   * Returns a list of allowed resource incoming API properties.
   *
   * @return array{string}
   *   An array of allowed resource properties.
   */
  abstract protected function getAllowedResourceProperties(): array;

  /**
   * Returns a list of required resource incoming API properties.
   *
   * @return array{string}
   *   An array of required resource properties.
   */
  abstract protected function getRequiredResourceProperties(): array;

  /**
   * Returns the bundle of the Drupal side entity that is related to the model.
   *
   * @return string
   *   The entity bundle.
   */
  abstract public function getEntityBundle(): string;

  /**
   * {@inheritdoc}
   */
  public function getEntityValues(): array {
    $values = $this->entityValues;
    $values['type'] = $this->getEntityBundle();
    return $values;
  }

  /**
   * Returns an initialized instance of the appropriate model.
   *
   * @param array $data
   *   The data to be parsed.
   * @param \Drupal\assessments\Model\AssessmentModelInterface|null $parent
   *   The parent model.
   * @param string $type
   *   The type of the model.
   *
   * @return \Drupal\assessments\Model\AssessmentModelInterface
   *   The initialized model.
   */
  public static function createFromContext(array $data, ?AssessmentModelInterface $parent = NULL, string $type = 'assessment'): AssessmentModelInterface {
    // @todo Move to the interface as a constant.
    $mapping = [
      'affects' => PublicServiceModel::class,
      'assessment' => AssessmentModel::class,
      'asset' => AssetModel::class,
      'binding_requirement' => BindingRequirementModel::class,
      'documents' => DocumentModel::class,
      'expression' => ExpressionModel::class,
      'has_participation' => ParticipationModel::class,
      'organisation' => OrganisationModel::class,
      'results_in' => StatementModel::class,
    ];

    return new $mapping[$type]($data, $parent);
  }

}
