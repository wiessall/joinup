<?php

declare(strict_types=1);

namespace Drupal\assessments\Plugin\rest\resource;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItemInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\assessments\AssessmentInterface;
use Drupal\assessments\Entity\Assessment\Expression;
use Drupal\assessments\Entity\Assessment\Participation;
use Drupal\assessments\Entity\Assessment\PublicOrganisation;
use Drupal\assessments\Entity\Assessment\PublicService;
use Drupal\assessments\Entity\Assessment\Report;
use Drupal\assessments\Entity\AssessmentStatement;
use Drupal\joinup_api\OpenApiSchemaValidator;
use Drupal\joinup_solution\Entity\SolutionInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\rest\Attribute\RestResource;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a REST resource to consume Assessment composer payloads.
 */
#[RestResource(
  id: "assessment_report",
  label: new TranslatableMarkup("Assessment report"),
  uri_paths: [
    "create" => "/api/assessments/v1/assessment",
  ],
)]
class AssessmentReportResource extends ResourceBase {

  /**
   * Assessment storage static cache.
   */
  protected ContentEntityStorageInterface $assessmentStorage;

  /**
   * An array of errors encountered during processing.
   */
  protected array $errors = [];

  /**
   * The report's language code.
   */
  protected string $langcode;

  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    array $serializerFormats,
    LoggerInterface $logger,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly OpenApiSchemaValidator $validator,
    protected readonly EntityRepositoryInterface $entityRepository,
    protected readonly EntityFieldManagerInterface $fieldManager,
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition, $serializerFormats, $logger);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('assessments'),
      $container->get('entity_type.manager'),
      $container->get(OpenApiSchemaValidator::class),
      $container->get('entity.repository'),
      $container->get('entity_field.manager'),
    );
  }

  /**
   * Responds to POST requests.
   *
   * Accepts a single JSON object representing the Assessment and related
   * entities. Currently, this is a pass-through that echoes the received data
   * with a 201 status.
   *
   * @param array $data
   *   The JSON payload. See assessments.openapi.yaml for the JSON schema.
   *
   * @see web/modules/custom/assessments/openapi/assessments.openapi.yaml
   */
  public function post(array $data): ResourceResponse {
    // Check data against the schema defined in the OpenAPI swagger.
    if ($errors = $this->getOpenApiSchemaErrors($data)) {
      return new ResourceResponse(['errors' => $errors], 422);
    }

    $report = $this->createReport($data);

    if ($this->errors) {
      return new ResourceResponse(['errors' => array_unique($this->errors)], 422);
    }

    $report->save();

    $response = new ResourceResponse($this->reportToArray($report), 201);
    $response->addCacheableDependency((new CacheableMetadata())->setCacheMaxAge(0));

    return $response;
  }

  /**
   * Returns the OpenAPI representation of an assessment report entity.
   *
   * @param \Drupal\assessments\Entity\Assessment\Report $report
   *   The assessments report entiy.
   * @param string|null $langcode
   *   (optional) If passed, loads a specific language version of the report.
   */
  protected function reportToArray(Report $report, ?string $langcode = NULL): array {
    if ($langcode) {
      $report = $report->getTranslation($langcode);
    }

    $provider = $report->get('provider')->entity;
    assert($provider instanceof PublicOrganisation);

    $bindingRequirements = [];
    foreach ($report->get('binding_requirement')->referencedEntities() as $requirement) {
      assert($requirement instanceof ParagraphInterface);

      $expressions = [];
      foreach ($requirement->get('expression')->referencedEntities() as $item) {
        assert($item instanceof Expression);
        $expressions[] = [
          'name' => $item->label(),
          'description' => $item->get('description')?->value,
          'documented_in' => $item->get('documented_in')?->uri,
        ];
      }

      $affects = [];
      foreach ($requirement->get('affects')->referencedEntities() as $item) {
        assert($item instanceof PublicService);
        $affects[] = [
          'name' => $item->label(),
          'thematic_area' => $this->itemListToValue($item->get('thematic_area'), 'id'),
        ];
      }

      $hasParticipation = [];
      foreach ($requirement->get('has_participation')->referencedEntities() as $item) {
        assert($item instanceof Participation);
        $hasParticipation[] = [
          'participant_type' => $this->itemListToValue($item->get('participant_type'), 'id'),
          'country' => $this->itemListToValue($item->get('stakeholder_country'), 'code'),
        ];
      }

      $assets = [];
      foreach ($requirement->get('asset')->referencedEntities() as $item) {
        assert($item instanceof SolutionInterface);
        $assets[] = [
          'id' => $item->uuid(),
          'title' => $item->label(),
          'description' => (string) $item->get('description')->processed,
          'landing_page' => $item->get('landing_page')->uri,
          'url' => $item->toUrl()->setAbsolute()->toString(),
        ];
      }

      $bindingRequirements[] = [
        'expression' => $expressions,
        'description' => $requirement->get('description')->value,
        'documents' => $this->itemListToValue($requirement->get('requirement_documents')),
        'affects' => $affects,
        'has_participation' => $hasParticipation,
        'asset' => $assets,
      ];
    }

    $resultsIn = [];
    foreach (['legal', 'organisational', 'semantic', 'technical'] as $type) {
      if (!$report->get($type)->isEmpty()) {
        $item = $report->get($type)->entity;
        assert($item instanceof AssessmentStatement);
        $resultsIn[$type] = [
          'interpretation' => $this->itemListToValue($item->get('interpretation'), 'id'),
          'judgement' => $item->get('judgement')->value,
        ];
      }
    }

    return [
      'id' => $report->uuid(),
      'language' => $report->language()->getId(),
      'name' => $report->label(),
      'provider' => [
        'id' => $provider->uuid(),
        'name' => $provider->label(),
        'organisation_type' => $provider->get('organisation_type')->entity->get('id')->value,
        'country' => $provider->get('country')->entity?->get('code')->value,
      ],
      'relevant_report' => $this->itemListToValue($report->get('relevant_report')),
      'binding_requirement' => $bindingRequirements,
      'results_in' => $resultsIn,
      'remaining_barriers' => $report->get('remaining_barriers')->value,
      'documents' => $this->itemListToValue($report->get('documents')),
      'other_comment' => $report->get('other_comment')->value,
    ];
  }

  /**
   * Validates $data against the schema and returns potenatial errors.
   *
   * @param array $data
   *   Structured array to be validated.
   */
  protected function getOpenApiSchemaErrors(mixed $data): array {
    return $this->validator->validate($data, __DIR__ . '/../../../../openapi/assessments.openapi.yaml', 'Assessment');
  }

  /**
   * Creates and returns a report given the decoded JSON payload.
   *
   * @param array $data
   *   Data structured array.
   *
   * @returns \Drupal\assessments\Entity\Assessment\Report
   *   The assessment report.
   */
  protected function createReport(array $data): Report {
    // Make sure we start on clean if the plugin is reused in the same request.
    $this->errors = [];
    $this->langcode = $data['language'] ?? 'en';

    $statementStorage = $this->entityTypeManager->getStorage('assessment_statement');

    $report = $this->getAssessmentStorage()->create([
      'type' => 'report',
      'langcode' => $this->langcode,
      'name' => $data['name'],
      'provider' => $this->getPublicOrganisation($data['provider']),
      'relevant_report' => $data['relevant_report'] ?? [],
      'binding_requirement' => $this->createBindingRequirements($data['binding_requirement']),
      'remaining_barriers' => $this->buildFormattedText($data, 'remaining_barriers'),
      // @todo Insert here report's documents.
      'other_comment' => $this->buildFormattedText($data, 'other_comment'),
    ]);

    // Add statements.
    foreach ($data['results_in'] ?? [] as $statementType => $result) {
      $report->{$statementType} = $statementStorage->create([
        'type' => $statementType,
        'langcode' => $this->langcode,
        'interpretation' => $this->getTermIdFromIdentifier('assess_statement_interpretation', $result['interpretation']),
        'judgement' => $this->buildFormattedText($result, 'judgement'),
      ]);
    }

    return $report;
  }

  /**
   * Links and, if needed creates, a public organisation.
   *
   * @param array|string $provider
   *   Either the public organisation data, or an existing entity UUID.
   *
   * @returns \Drupal\assessments\Entity\Assessment\PublicOrganisation
   *   The public organisation.
   */
  protected function getPublicOrganisation(array|string $provider): PublicOrganisation|string|int|null {
    if (is_string($provider)) {
      $organisation = $this->entityRepository->loadEntityByUuid('assessment', $provider);
      if (!$organisation) {
        $this->errors[] = "A public organisation with ID '$provider' does not exist";
      }
      return $organisation;
    }

    return $this->getAssessmentStorage()->create([
      'type' => 'public_organisation',
      'langcode' => $this->langcode,
      'name' => $provider['name'],
      'organisation_type' => $this->getTermIdFromIdentifier('assess_provider_type', $provider['organisation_type']),
      'country' => isset($provider['country']) ? $this->getTermIdFromIdentifier('spatial', $provider['country'], 'code') : NULL,
    ]);
  }

  /**
   * Creates a list of binding requirements paragraphs.
   *
   * @param array $requirements
   *   The binding requiremente definitions.
   *
   * @return list<\Drupal\paragraphs\ParagraphInterface>
   *   A list of binding requirements paragraphs.
   */
  protected function createBindingRequirements(array $requirements): array {
    $storage = $this->entityTypeManager->getStorage('paragraph');
    $paragraphs = [];

    foreach ($requirements as $requirement) {
      $paragraphs[] = $storage->create([
        'type' => 'binding_requirement',
        'expression' => $this->createExpressions($requirement['expression']),
        'description' => $this->buildFormattedText($requirement, 'description'),
        // @todo Put here the files.
        'affects' => $this->createPublicServices($requirement['affects']),
        'has_participation' => $this->createParticipation($requirement['has_participation'] ?? []),
        'asset' => $this->getAssets($requirement['asset'] ?? []),
      ]);
    }

    return $paragraphs;
  }

  /**
   * Creates a list of expression entities.
   *
   * @param array $expressions
   *   List of expresion definitions.
   *
   * @return list<\Drupal\assessments\Entity\Assessment\Expression>
   *   A list of expression entities.
   */
  protected function createExpressions(array $expressions): array {
    return array_map(function ($expression): Expression {
      return $this->getAssessmentStorage()->create([
        'type' => 'expression',
        'langcode' => $this->langcode,
        'name' => $expression['name'],
        'description' => $this->buildFormattedText($expression, 'description'),
        'documented_in' => $expression['documented_in'],
      ]);
    }, $expressions);
  }

  /**
   * Creates a list of public service entities.
   *
   * @param array $affects
   *   List of public service definitions.
   *
   * @return list<\Drupal\assessments\Entity\Assessment\PublicService>
   *   A list of public service entities.
   */
  protected function createPublicServices(array $affects): array {
    return array_map(function ($item): PublicService {
      return $this->getAssessmentStorage()->create([
        'type' => 'public_service',
        'langcode' => $this->langcode,
        'name' => $item['name'],
        'thematic_area' => $this->getTermIdFromIdentifier('assess_public_service_area', $item['thematic_area']),
      ]);
    }, $affects);
  }

  /**
   * Creates a list of participation entities.
   *
   * @param array $hasParticipation
   *   List of participation definitions.
   *
   * @return list<\Drupal\assessments\Entity\Assessment\Participation>
   *   A list of participation entities.
   */
  protected function createParticipation(array $hasParticipation): array {
    return array_map(function ($item): Participation {
      return $this->getAssessmentStorage()->create([
        'type' => 'participation',
        'langcode' => $this->langcode,
        'participant_type' => $this->getTermIdFromIdentifier('assess_participant_type', $item['participant_type']),
        'stakeholder_country' => $this->getTermIdFromIdentifier('spatial', $item['country'], 'code'),
      ]);
    }, $hasParticipation);
  }

  /**
   * Returns and, if necessary, creates a list of solution entities.
   *
   * @param array $assets
   *   List of, either existing solution UUIDs, or solution definitions.
   *
   * @return list<\Drupal\joinup_solution\Entity\SolutionInterface>
   *   A list of solution entities.
   */
  protected function getAssets(array $assets): array {
    $storage = $this->entityTypeManager->getStorage('node');

    $solutions = [];
    foreach ($assets as $asset) {
      if (is_string($asset)) {
        $solution = $this->entityRepository->loadEntityByUuid('node', $asset);
        if (!$solution) {
          $this->errors[] = "An asset (interoperability solution) with ID '$asset' does not exist";
        }
        $solutions[] = $solution;
        continue;
      }

      $solutions[] = $storage->create([
        'type' => 'solution',
        'langcode' => $this->langcode,
        'og_audience' => $this->entityRepository->loadEntityByUuid('node', AssessmentInterface::SOLUTION_COLLECTOR),
        'title' => $asset['title'],
        'description' => $this->buildFormattedText($asset, 'description', 'content_editor'),
        'landing_page' => $asset['landing_page'],
      ]);
    }

    return $solutions;
  }

  /**
   * Converts terms identifiers to their corrsponding term ID.
   *
   * @param string $vid
   *   The vocabulary ID.
   * @param array|string $identifier
   *   One or many term identifiers.
   * @param string $field
   *   The field contraining the identifier.
   *
   * @returns list<string|int>
   *   A list of term IDs.
   */
  protected function getTermIdFromIdentifier(string $vid, array|string $identifier, string $field = 'id'): array {
    $identifiers = (array) $identifier;

    $tids = [];
    foreach ($identifiers as $identifier) {
      $ids = $this->entityTypeManager->getStorage('taxonomy_term')->getQuery()
        ->accessCheck(FALSE)
        ->condition('vid', $vid)
        ->condition($field, $identifier)
        ->execute();

      if (!$ids) {
        $this->errors[] = "A term with identifier '$identifier' does not exist in '$vid' vocabulary";
        continue;
      }

      $tids[] = reset($ids);
    }

    return $tids;
  }

  /**
   * Builds a formatted long text array.
   *
   * @param array $data
   *   The structure where the long text field belongs.
   * @param string $property
   *   The long text name.
   * @param string $format
   *   (optional) The format to be used. Defaults to plain_text.
   *
   * @return array{string, string}|null
   *   An array with 'value' and 'format', or NULL.
   */
  protected function buildFormattedText(array $data, string $property, string $format = 'plain_text'): array|null {
    if (isset($data[$property]) && is_string($data[$property])) {
      return ['value' => $data[$property], 'format' => $format];
    }
    return NULL;
  }

  /**
   * Returns a simplified version of a field content.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $itemList
   *   The field item list.
   * @param string|null $fieldName
   *   (optional) The field name of the referred entity. This is passed only
   *   when $itemList is an entity reference field and rep[resents the field
   *   whose value to be retrieved from the targe entity. It has no effect on
   *   other fields.
   *
   * @returns list<string>|string|null
   *   Depending on the field cardinality returns either one value, or a list of
   *   values.
   */
  protected function itemListToValue(FieldItemListInterface $itemList, ?string $fieldName = NULL): array|string|null {
    $definition = $itemList->getFieldDefinition()->getFieldStorageDefinition();
    $cardinality = $definition->getCardinality();
    $itemDefinition = $itemList->getItemDefinition();

    $isReference = $fieldName && is_a($itemDefinition->getClass(), EntityReferenceItemInterface::class, TRUE);
    if ($isReference) {
      $targetType = $definition->getSetting('target_type');
      $referenceDefinition = $this->fieldManager->getFieldStorageDefinitions($targetType)[$fieldName];
      $property = $referenceDefinition->getMainPropertyName();
    }
    else {
      $property = $itemList->getFieldDefinition()->getFieldStorageDefinition()->getMainPropertyName();
    }

    // Only support one main property.
    assert(is_string($property));

    $items = [];
    foreach ($itemList as $item) {
      $object = $isReference ? $item->entity->get($fieldName) : $item;
      $items[] = $object->$property;
    }

    return $cardinality == 1 ? reset($items) : $items;
  }

  /**
   * Returns and statically caches the assessments entity storage.
   *
   * @returns \Drupal\Core\Entity\ContentEntityStorageInterface
   *   The assessments entity storage.
   */
  protected function getAssessmentStorage(): ContentEntityStorageInterface {
    if (!isset($this->assessmentStorage)) {
      $storage = $this->entityTypeManager->getStorage('assessment');
      assert($storage instanceof ContentEntityStorageInterface);
      $this->assessmentStorage = $storage;
    }
    return $this->assessmentStorage;
  }

}
