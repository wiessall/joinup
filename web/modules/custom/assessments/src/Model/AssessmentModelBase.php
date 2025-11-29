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
   * The delta of the current model if referenced by another model.
   *
   * @var int|null
   */
  protected ?int $delta = NULL;

  /**
   * The parent model.
   *
   * @var \Drupal\assessments\Model\AssessmentModelInterface|null
   */
  protected ?AssessmentModelInterface $parent;

  /**
   * An array of child models indexed by their key.
   *
   * @var \Drupal\assessments\Model\AssessmentModelInterface[]
   */
  protected array $children = [];

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

  public function __construct(array $data = [], ?AssessmentModelInterface $parent = NULL, ?int $delta = NULL) {
    if (in_array('id', $this->getAllowedResourceProperties()) && !empty($data['id'])) {
      $this->isNew = FALSE;
    }
    else {
      $this->isNew = TRUE;
    }
    $this->delta = $delta;
    $this->parent = $parent;
  }

  /**
   * Calculates the path to the current key.
   * @param string $key
   *   The current path.
   *
   * @return string
   *   The calculated path.
   */
  protected function calculatePath(string $key): string {
    return $this->getModelPath() . ' > ' . $key;
  }

  /**
   * {@inheritdoc}
   */
  public function getModelPath(): string {
    $deltaPath = $this->delta === NULL ? '' : '[' . $this->delta . ']';
    $modelPath = $this->parent === NULL
      ? $this->getEntityTypeId()
      : $this->parent->getModelPath() . ' > ' . $this->getEntityBundle();
    return $modelPath . $deltaPath;
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
   * Returns the parent model.
   *
   * @return \Drupal\assessments\Model\AssessmentModelInterface|null
   *   The parent model or NULL.
   */
  public function getParent(): ?AssessmentModelInterface {
    return $this->parent;
  }

  /**
   * Returns the child models.
   *
   * @return \Drupal\assessments\Model\AssessmentModelInterface[]
   *   An array of child models.
   */
  public function getChildren(): array {
    return $this->children;
  }

  /**
   * Adds a child model.
   *
   * @param \Drupal\assessments\Model\AssessmentModelInterface $child
   *   The child model to add.
   */
  public function addChild(AssessmentModelInterface $child): void {
    $this->children[] = $child;
  }

  /**
   * {@inheritdoc}
   */
  public function addError(string $key, string $message): void {
    $path = $this->calculatePath($key);
    $this->errors[] = [
      'path' => $path,
      'message' => $message,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityValues(): array {
    $values = $this->entityValues;
    $values['type'] = $this->getEntityBundle();
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function addEntityValue(string $key, int|string $value): void {
    $this->entityValues[$key][] = $value;
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
  public static function createFromContext(array $data, ?AssessmentModelInterface $parent = NULL, string $type = 'assessment', ?int $delta = NULL): AssessmentModelInterface {
    // @todo Move to the interface as a constant.
    $mapping = [
      'affects' => PublicServiceModel::class,
      'assessment' => AssessmentModel::class,
      'asset' => AssetModel::class,
      'binding_requirement' => BindingRequirementModel::class,
      'documents' => DocumentModel::class,
      'expression' => ExpressionModel::class,
      'has_participation' => ParticipationModel::class,
      'provider' => OrganisationModel::class,
      'results_in' => StatementModel::class,
    ];

    return new $mapping[$type]($data, $parent, $delta);
  }

}
