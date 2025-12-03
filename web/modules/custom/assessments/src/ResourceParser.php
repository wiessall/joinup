<?php

declare(strict_types=1);

namespace Drupal\assessments;

use Drupal\Component\Uuid\Php;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\assessments\Entity\Assessment\Asset;
use Drupal\assessments\Entity\Assessment\Expression;
use Drupal\assessments\Entity\Assessment\Participation;
use Drupal\assessments\Entity\Assessment\PublicOrganisation;
use Drupal\assessments\Entity\Assessment\PublicService;
use Drupal\assessments\Entity\Assessment\Report;
use Drupal\assessments\Entity\AssessmentStatement;
use Drupal\assessments\Entity\Paragraph\BindingRequirement;
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
 * - The generated.
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

    // STEP 1: Validation-only pass — do not save anything.
    try {
      $this->validateSubEntry($data, 'assessment');
    }
    catch (\Throwable $e) {
      // Any unexpected exception during validation should surface as a 422.
      $this->errors[] = sprintf('Validation failed: %s', $e->getMessage());
    }

    // If there are validation errors, return early without saving.
    if (!empty($this->errors)) {
      return ['data' => $data, 'errors' => $this->errors];
    }

    // STEP 2: Persistence pass — create/update entities in a transaction.
    $transaction = $this->database->startTransaction();
    try {
      $report_id = $this->persistSubEntry($data, 'assessment');

      // Optionally keep a reference to the created/updated report entity.
      $loaded = Report::load($report_id);
      if ($loaded instanceof Report) {
        $this->assessmentReport = $loaded;
      }

      if (isset($data['results_in'])) {
        // Ensure the 'results_in' statements are linked back to this report.
        $statementIds = [];
        foreach ($data['results_in'] as $statementData) {
          $statement = AssessmentStatement::create([
            'type' => $statementData['type'],
            'judgement' => $statementData['judgement'],
          ]);
          $statement->set('interpretation', $statement->translateResourceValue('interpretation', $statementData['interpretation']));
          $violations = $statement->validate();
          foreach ($violations as $violation) {
            $this->errors[] = sprintf('assessment > results_in > %s: %s', $violation->getPropertyPath(), $violation->getMessage());
          }
          if (empty($this->errors)) {
            $statement->save();
            $statementIds[$statementData['type']][] = $statement->id();
          }
        }

        if (!empty($statementIds)) {
          foreach ($statementIds as $fieldName => $ids) {
            $this->assessmentReport->set($fieldName, $ids);
            $violations = $this->assessmentReport->get($fieldName)->validate();
            foreach ($violations as $violation) {
              $this->errors[] = sprintf('assessment > %s: %s', $fieldName, $violation->getMessage());
            }
          }
          $this->assessmentReport->save();
        }
      }

      if (!empty($this->errors)) {
        // Any errors discovered during persistence (e.g., field violations)
        // cause a rollback and will be returned to the client.
        $transaction->rollBack();
      }
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
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
      'expression' => Expression::class,
      'participation' => Participation::class,
      'has_participation' => Participation::class,
      'public_organisation' => PublicOrganisation::class,
      'provider' => PublicOrganisation::class,
      'affects' => PublicService::class,
      'binding_requirement' => BindingRequirement::class,
    ];
    return $mapping[$key] ?? NULL;
  }

  /**
   * Parses a sub-entry and returns its ID.
   *
   * @param array $data
   *   The data to parse.
   * @param string $key
   *   The reference key.
   * @param string $basePath
   *   The base path for error reporting. Default is empty as the key will be
   *   appended.
   *
   * @return string|int
   *   The ID of the created or existing entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function parseSubEntry(array &$data, string $key, string $basePath = ''): string|int {
    // Backwards-compatible wrapper. The actual implementation now lives in
    // persistSubEntry() as part of the two-phase processing (validate +
    // persist).
    return $this->persistSubEntry($data, $key, $basePath);
  }

  /**
   * First pass: validate inbound data without creating/saving entities.
   *
   * - Ensures only allowed properties exist in the JSON (based on mappings).
   * - Ensures required properties are present and not empty.
   * - Recursively validates nested references, including provided IDs.
   * - Does NOT perform field constraint validations or any persistence.
   */
  protected function validateSubEntry(array &$data, string $key, string $basePath = ''): void {
    $entity = $this->initializeObject($key);
    $mappings = method_exists($entity, 'getResourcePropertyMappings') ? $entity->getResourcePropertyMappings() : [];

    // Allowed properties are those defined in mappings plus the inbound 'id'.
    $allowedProperties = array_keys($mappings);
    $allowedProperties[] = 'id';
    foreach ($data as $property => $value) {
      if (!in_array($property, $allowedProperties, TRUE)) {
        $this->errors[] = sprintf('%s: Unexpected property: %s', $basePath . $key, (string) $property);
      }
    }

    // Check required properties are present and not empty.
    foreach ($mappings as $property => $definition) {
      // Skip special-case statements if needed (mirrors persist pass).
      if ($property === 'results_in' && $key === 'assessment') {
        continue;
      }
      if (!empty($definition['is_required'])) {
        if (!array_key_exists($property, $data) || $data[$property] === NULL || $data[$property] === '' || $data[$property] === []) {
          $this->errors[] = sprintf('%s: Missing required property: %s', $basePath . $key, $property);
        }
      }
    }

    // Validate reference children structure and recurse.
    foreach ($mappings as $property => $definition) {
      if (empty($definition['is_reference'])) {
        continue;
      }
      $children = $data[$property] ?? [];
      if ($children === NULL || $children === '') {
        // Nothing to validate.
        continue;
      }
      if (!is_array($children)) {
        $this->errors[] = sprintf('%s > %s: Expected an array of sub-entries', $basePath . $key, $property);
        continue;
      }
      foreach ($children as &$subentry) {
        // If an explicit ID is provided, ensure it references the correct
        // entity type and exists.
        if (is_array($subentry) && !empty($subentry['id'])) {
          $expectedClass = $this->resolveClassFromKey($property);
          if ($expectedClass) {
            $loaded = $expectedClass::load($subentry['id']);
            if (!($loaded instanceof $expectedClass)) {
              $this->errors[] = sprintf('%s > %s: Provided id %s does not reference a valid %s entity', $basePath . $key, $property, (string) $subentry['id'], (new \ReflectionClass($expectedClass))->getShortName());
              continue;
            }
          }
        }
        // Recurse for embedded new/updated entities.
        elseif (is_array($subentry)) {
          $this->validateSubEntry($subentry, $property, $basePath . $key . ' > ');
        }
        else {
          $this->errors[] = sprintf('%s > %s: Invalid sub-entry format', $basePath . $key, $property);
        }
      }
      unset($subentry);
    }
  }

  /**
   * Second pass: persist entities (create/update) from leaf to root.
   *
   * Performs field-level Drupal validations and saves entities, writing back
   * generated IDs to the inbound structure. Assumes structural validation has
   * already succeeded in the first pass.
   *
   * @return string|int
   *   The ID of the created or existing entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function persistSubEntry(array &$data, string $key, string $basePath = ''): string|int {
    $entity = $this->initializeObject($key);
    $mappings = method_exists($entity, 'getResourcePropertyMappings') ? $entity->getResourcePropertyMappings() : [];

    // Handle scalar (non-reference) properties using field mappings.
    foreach ($mappings as $property => $definition) {
      if (!empty($definition['is_reference'])) {
        continue;
      }
      if ($property === 'id') {
        continue;
      }
      $fieldName = $definition['field_name'] ?? $property;
      if (!$entity->hasField($fieldName)) {
        $this->errors[] = sprintf('%s: Unknown property mapping: %s \u2192 %s', $basePath . $key, $property, $fieldName);
        continue;
      }
      if (!array_key_exists($property, $data) || $data[$property] === NULL || $data[$property] === '') {
        continue;
      }
      $translated = $entity->translateResourceValue($fieldName, $data[$property]);
      if ($translated === NULL) {
        $this->errors[] = sprintf('%s > %s: Unable to translate value "%s"', $basePath . $key, $property, $data[$property]);
        continue;
      }
      $entity->set($fieldName, $translated);
      $violations = $entity->get($fieldName)->validate();
      foreach ($violations as $violation) {
        $this->errors[] = sprintf('%s > %s: %s', $basePath . $key, $fieldName, $violation->getMessage());
      }
    }

    // Handle reference (nested) properties using field mappings.
    foreach ($mappings as $property => $definition) {
      // Statements (results_in) are handled as a special case.
      if ($property === 'results_in' && $key === 'assessment') {
        continue;
      }
      if (empty($definition['is_reference'])) {
        continue;
      }
      $fieldName = $definition['field_name'] ?? $property;
      if (!$entity->hasField($fieldName)) {
        $this->errors[] = sprintf('%s: Unknown property mapping: %s \u2192 %s', $basePath . $key, $property, $fieldName);
        continue;
      }
      $values = [];
      $children = $data[$property] ?? [];
      if (!is_array($children)) {
        $this->errors[] = sprintf('%s > %s: Expected an array of sub-entries', $basePath . $key, $property);
        $children = [];
      }
      foreach ($children as &$subentry) {
        if (is_array($subentry) && !empty($subentry['id'])) {
          $expectedClass = $this->resolveClassFromKey($property);
          if ($expectedClass) {
            $loaded = $expectedClass::load($subentry['id']);
            if ($loaded instanceof $expectedClass) {
              $values[] = $loaded->id();
              continue;
            }
            $this->errors[] = sprintf('%s > %s: Provided id %s does not reference a valid %s entity', $basePath . $key, $property, (string) $subentry['id'], (new \ReflectionClass($expectedClass))->getShortName());
            continue;
          }
        }
        $values[] = $this->persistSubEntry($subentry, $property, $basePath . $key . ' > ');
      }
      unset($subentry);
      $translated = $entity->translateResourceValue($fieldName, $values);
      $entity->set($fieldName, $translated);
      $violations = $entity->get($fieldName)->validate();
      foreach ($violations as $violation) {
        $this->errors[] = sprintf('%s > %s: %s', $basePath . $key, $violation->getPropertyPath(), $violation->getMessage());
      }
    }

    $entity->save();
    $data['id'] = $entity->id();
    return $entity->id();
  }

}
