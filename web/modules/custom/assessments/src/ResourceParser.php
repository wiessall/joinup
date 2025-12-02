<?php

declare(strict_types=1);

namespace Drupal\assessments;

use Drupal\assessments\Entity\Assessment\Asset;
use Drupal\assessments\Entity\Assessment\Participation;
use Drupal\assessments\Entity\Assessment\PublicOrganisation;
use Drupal\assessments\Entity\Assessment\PublicService;
use Drupal\assessments\Entity\Assessment\Report;
use Drupal\assessments\Entity\AssessmentStatement;
use Drupal\assessments\Entity\Paragraph\BindingRequirement;
use Drupal\Component\Uuid\Php;
use Drupal\assessments\Model\AssessmentModel;
use Drupal\assessments\Model\AssessmentModelInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityStorageException;
use Github\Api\Repo;
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
   * An array of errors.
   *
   * @var array
   */
  protected array $errors = [];

  /**
   * The assessment report to be created.
   *
   * @var \Drupal\assessments\Entity\Assessment\Report
   */
  protected Report $assessmentReport;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'uuid')]
    protected Php $uuid,
    protected Connection $database,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function parseInbound(array &$data): array {
    // Reset error state for this invocation.
    $this->errors = [];
    // Start a transaction for the full parse/create process.
    $transaction = $this->database->startTransaction();
    try {
      // Reuse the generic sub-entry parser for the root assessment report.
      $report_id = $this->parseSubEntry($data, 'assessment');

      // Optionally keep a reference to the created/updated report entity.
      $loaded = Report::load($report_id);
      if ($loaded instanceof Report) {
        $this->assessmentReport = $loaded;
      }

      // If validation errors were collected during parsing, rollback.
      if (!empty($this->errors)) {
        $transaction->rollBack();
      }
    }
    catch (\Throwable $e) {
      // Rollback in case of any exception and rethrow.
      $transaction->rollBack();
      throw $e;
    }

    return ['data' => $data, 'errors' => $this->errors];
  }

  /**
   * Initializes an entity object based on the reference key.
   *
   * @param string $key
   *   The reference key.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The initialized entity object.
   */
  protected function initializeObject(string $key): ContentEntityInterface {
    $class = $this->resolveClassFromKey($key);
    if (!$class) {
      $this->errors[] = sprintf('Unknown entity reference key: %s', $key);
      throw new \InvalidArgumentException(sprintf('Unknown reference key: %s', $key));
    }
    // Some entities use a different bundle name than the reference key.
    $bundle = match ($key) {
      'assessment' => 'report',
      default => $key,
    };
    return $class::create(['type' => $bundle]);
  }

  /**
   * Resolves the expected entity class for a reference key.
   */
  protected function resolveClassFromKey(string $key): ?string {
    $mapping = [
      'assessment' => Report::class,
      'statement' => AssessmentStatement::class,
      'results_in' => AssessmentStatement::class,
      'asset' => Asset::class,
      'documents' => Asset::class,
      'expression' => \Drupal\assessments\Entity\Assessment\Expression::class,
      'participation' => Participation::class,
      'has_participation' => Participation::class,
      'public_organisation' => PublicOrganisation::class,
      'provider' => PublicOrganisation::class,
      'affects' => PublicService::class,
      'binding_requirement' => BindingRequirement::class,
    ];
    return $mapping[$key] ?? null;
  }

  /**
   * Parses a sub-entry and returns its ID.
   *
   * @param array $data
   *   The data to parse.
   * @param string $key
   *   The reference key.
   *
   * @return string|int
   *   The ID of the created or existing entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function parseSubEntry(array &$data, string $key): string|int {
    $entity = $this->initializeObject($key);
    $mappings = method_exists($entity, 'getResourcePropertyMappings') ? $entity->getResourcePropertyMappings() : [];

    // Validate required properties based on mappings.
    foreach ($mappings as $property => $definition) {
      if (!empty($definition['is_required']) && empty($data[$property])) {
        $this->errors[] = sprintf('%s: Missing required property: %s', $key, $property);
      }
    }

    // Handle scalar (non-reference) properties using field mappings.
    foreach ($mappings as $property => $definition) {
      if (!empty($definition['is_reference'])) {
        continue;
      }
      // Special property 'id' is supported inbound but should not be set.
      if ($property === 'id') {
        continue;
      }
      $fieldName = $definition['field_name'] ?? $property;
      if (!$entity->hasField($fieldName)) {
        $this->errors[] = sprintf('%s: Unknown property mapping: %s -> %s', $key, $property, $fieldName);
        continue;
      }
      if (!array_key_exists($property, $data) || $data[$property] === null || $data[$property] === '') {
        continue;
      }
      $translated = method_exists($entity, 'translateResourceValue')
        ? $entity->translateResourceValue($fieldName, $data[$property])
        : $data[$property];
      $entity->set($fieldName, $translated);
      $violations = $entity->get($fieldName)->validate();
      foreach ($violations as $violation) {
        $this->errors[] = sprintf('%s > %s: %s', $key, $violation->getPropertyPath(), $violation->getMessage());
      }
    }

    // Handle reference (nested) properties using field mappings.
    foreach ($mappings as $property => $definition) {
      if (empty($definition['is_reference'])) {
        continue;
      }
      $fieldName = $definition['field_name'] ?? $property;
      if (!$entity->hasField($fieldName)) {
        $this->errors[] = sprintf('%s: Unknown property mapping: %s -> %s', $key, $property, $fieldName);
        continue;
      }
      $values = [];
      $children = $data[$property] ?? [];
      if (!is_array($children)) {
        $this->errors[] = sprintf('%s > %s: Expected an array of sub-entries', $key, $property);
        $children = [];
      }
      foreach ($children as &$subentry) {
        // If an explicit ID is provided for a reference, try to load it and
        // short-circuit without deeper parsing.
        if (is_array($subentry) && !empty($subentry['id'])) {
          $expectedClass = $this->resolveClassFromKey($property);
          if ($expectedClass) {
            $loaded = $expectedClass::load($subentry['id']);
            if ($loaded instanceof $expectedClass) {
              $values[] = $loaded->id();
              // No further processing for this subentry.
              continue;
            }
            // Provided ID not found or wrong type.
            $this->errors[] = sprintf('%s > %s: Provided id %s does not reference a valid %s entity', $key, $property, (string) $subentry['id'], (new \ReflectionClass($expectedClass))->getShortName());
            // Skip further processing for this invalid reference.
            continue;
          }
        }
        // Fallback to recursive parsing when no valid id provided.
        $values[] = $this->parseSubEntry($subentry, $property);
      }
      unset($subentry);
      $translated = method_exists($entity, 'translateResourceValue')
        ? $entity->translateResourceValue($fieldName, $values)
        : $values;
      $entity->set($fieldName, $translated);
      $violations = $entity->get($fieldName)->validate();
      foreach ($violations as $violation) {
        $this->errors[] = sprintf('%s > %s > %s: %s', $key, $property, $violation->getPropertyPath(), $violation->getMessage());
      }
    }

    $entity->save();
    // Write back the generated ID into the current sub-entry data.
    $data['id'] = $entity->id();
    return $entity->id();
  }
}
