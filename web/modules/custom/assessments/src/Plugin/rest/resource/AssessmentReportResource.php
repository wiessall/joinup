<?php

declare(strict_types=1);

namespace Drupal\assessments\Plugin\rest\resource;

use Drupal\assessments\AssessmentVocabularyHelper;
use Drupal\Component\Render\PlainTextOutput;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItemInterface;
use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\Exception\FileExistsException;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Lock\LockAcquiringException;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\assessments\AssessmentInterface;
use Drupal\assessments\Entity\AssessmentReport;
use Drupal\assessments\Entity\AssessmentStatement;
use Drupal\assessments\Entity\PublicOrganisation;
use Drupal\file\FileInterface;
use Drupal\file\Plugin\Field\FieldType\FileFieldItemList;
use Drupal\file\Upload\FileUploadHandler;
use Drupal\file\Upload\FileUploadLocationTrait;
use Drupal\file\Upload\InputStreamUploadedFile;
use Drupal\file\Validation\FileValidatorSettingsTrait;
use Drupal\joinup_api\OpenApiSchemaValidator;
use Drupal\joinup_solution\Entity\SolutionInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\rest\Attribute\RestResource;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\File\Exception\CannotWriteFileException;
use Symfony\Component\HttpFoundation\File\Exception\NoFileException;
use Symfony\Component\HttpFoundation\File\Exception\UploadException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Provides a REST resource to consume Assessment composer payloads.
 */
#[RestResource(
  id: "assessment_report",
  label: new TranslatableMarkup("Assessment report"),
  uri_paths: [
    "canonical" => "/api/assessments/v1/assessment/{uuid}",
    "create" => "/api/assessments/v1/assessment",
  ],
)]
class AssessmentReportResource extends ResourceBase {

  use FileUploadLocationTrait;
  use FileValidatorSettingsTrait;

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
    protected readonly ClientInterface $httpClient,
    protected readonly FileSystemInterface $fileSystem,
    protected readonly FileUploadHandler $uploadHandler,
    protected readonly AccountProxyInterface $currentUser,
    #[Autowire(AssessmentVocabularyHelper::class)]
    protected readonly AssessmentVocabularyHelper $assessmentVocabularyHelper,
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
      $container->get('http_client'),
      $container->get('file_system'),
      $container->get('file.upload_handler'),
      $container->get('current_user'),
      $container->get(AssessmentVocabularyHelper::class),
    );
  }

  /**
   * Responds to GET request.
   *
   * @param string $uuid
   *   The UUID of the assessment report.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The assessment report structured according to the OpenAPI schema.
   */
  public function get(string $uuid): ResourceResponse {
    $report = $this->entityRepository->loadEntityByUuid('assessment', $uuid);

    if ($report instanceof AssessmentReport) {
      return (new ResourceResponse($this->reportToArray($report), 200))
        ->addCacheableDependency($report);
    }

    return new ResourceResponse(['errors' => ['Not found']], 404);
  }

  /**
   * Responds to POST requests.
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
      ksort($this->errors);
      return new ResourceResponse(['errors' => $this->errors], 422);
    }

    $report->save();

    $response = new ResourceResponse($this->reportToArray($report), 201);
    $response->addCacheableDependency((new CacheableMetadata())->setCacheMaxAge(0));

    return $response;
  }

  /**
   * Returns the OpenAPI representation of an assessment report entity.
   *
   * @param \Drupal\assessments\Entity\AssessmentReport $report
   *   The assessments report entity.
   * @param string|null $langcode
   *   (optional) If passed, loads a specific language version of the report.
   */
  protected function reportToArray(AssessmentReport $report, ?string $langcode = NULL): array {
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
        assert($item instanceof AssessmentInterface);
        $expressions[] = [
          'name' => $item->get('initiative_name')->value,
          'description' => $item->get('description')?->value,
          'documented_in' => $item->get('documented_in')?->uri,
        ];
      }

      $affects = [];
      foreach ($requirement->get('affects')->referencedEntities() as $item) {
        assert($item instanceof AssessmentInterface);
        $affects[] = [
          'name' => $item->label(),
          'thematic_area' => $this->itemListToValue($item->get('thematic_area'), 'id'),
          'country' => $this->itemListToValue($item->get('countries'), 'code'),
        ];
      }

      $hasParticipation = [];
      foreach ($requirement->get('has_participation')->referencedEntities() as $item) {
        assert($item instanceof AssessmentInterface);
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
          'uri' => $item->getUri(),
          'title' => $item->label(),
          'description' => (string) $item->get('description')->processed,
          'landing_page' => $item->get('landing_page')->uri,
          'url' => $item->toUrl()->setAbsolute()->toString(),
        ];
      }

      $bindingRequirements[] = [
        'expression' => $expressions,
        'description' => $requirement->get('description')->value,
        'documents' => $this->fileUrls($requirement->get('requirement_documents')),
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
      'uri' => $report->getUri(),
      'language' => $report->language()->getId(),
      'name' => $report->label(),
      'provider' => [
        'id' => $provider->uuid(),
        'uri' => $provider->getUri(),
        'name' => $provider->label(),
        'organisation_type' => $provider->get('organisation_type')->entity->get('id')->value,
        'country' => $provider->get('country')->entity?->get('code')->value,
      ],
      'relevant_report' => $this->itemListToValue($report->get('relevant_report')),
      'binding_requirement' => $bindingRequirements,
      'results_in' => $resultsIn,
      'remaining_barriers' => $report->get('remaining_barriers')->value,
      'documents' => $this->fileUrls($report->get('documents')),
      'other_comment' => $report->get('other_comment')->value,
    ];
  }

  /**
   * Validates $data against the schema and returns potential errors.
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
   * @returns \Drupal\assessments\Entity\AssessmentReport
   *   The assessment report.
   */
  protected function createReport(array $data): AssessmentReport {
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
      'other_comment' => $this->buildFormattedText($data, 'other_comment'),
      'state' => 'published',
    ]);

    // Add statements.
    foreach ($data['results_in'] ?? [] as $statementType => $result) {
      $report->{$statementType} = $statementStorage->create([
        'type' => $statementType,
        'langcode' => $this->langcode,
        'interpretation' => $this->getTermIdByName('assess_statement_interpretation', $result['interpretation'], "/results_in/$statementType/interpretation"),
        'judgement' => $this->buildFormattedText($result, 'judgement'),
      ]);
    }

    $this->uploadDocuments($report, $data);

    return $report;
  }

  /**
   * Links and, if needed creates, a public organisation.
   *
   * @param array|string $provider
   *   Either the public organisation data, or an existing entity UUID.
   *
   * @returns \Drupal\assessments\Entity\PublicOrganisation
   *   The public organisation.
   */
  protected function getPublicOrganisation(array|string $provider): PublicOrganisation|string|int|null {
    if (is_string($provider)) {
      $organisation = $this->entityRepository->loadEntityByUuid('assessment', $provider);
      if (!$organisation) {
        $this->addError('/provider', "A public organisation with ID '$provider' does not exist");
      }
      return $organisation;
    }

    return $this->getAssessmentStorage()->create([
      'type' => 'public_organisation',
      'langcode' => $this->langcode,
      'name' => $provider['name'],
      'organisation_type' => $this->getTermIdByName('assess_provider_type', $provider['organisation_type'], "/provider/organisation_type"),
      'country' => isset($provider['country']) ? $this->getTermIdByName('spatial', $provider['country'], "/provider/country", 'code') : NULL,
      'status' => TRUE,
    ]);
  }

  /**
   * Creates a list of binding requirements paragraphs.
   *
   * @param array $requirements
   *   The binding requirement definitions.
   *
   * @return list<\Drupal\paragraphs\ParagraphInterface>
   *   A list of binding requirements paragraphs.
   */
  protected function createBindingRequirements(array $requirements): array {
    $storage = $this->entityTypeManager->getStorage('paragraph');
    $paragraphs = [];

    foreach ($requirements as $delta => $requirement) {
      $path = "/binding_requirements/$delta";
      $paragraphs[] = $storage->create([
        'type' => 'binding_requirement',
        'expression' => $this->createExpressions($requirement['expression']),
        'description' => $this->buildFormattedText($requirement, 'description'),
        'affects' => $this->createPublicServices($requirement['affects'], $path),
        'has_participation' => $this->createParticipation($requirement['has_participation'] ?? [], $path),
        'asset' => $this->getAssets($requirement['asset'] ?? [], $path),
      ]);
    }

    return $paragraphs;
  }

  /**
   * Creates a list of expression entities.
   *
   * @param array $expressions
   *   List of expression definitions.
   *
   * @return list<\Drupal\assessments\AssessmentInterface>
   *   A list of expression entities.
   */
  protected function createExpressions(array $expressions): array {
    return array_map(function ($expression): AssessmentInterface {
      return $this->getAssessmentStorage()->create([
        'type' => 'expression',
        'langcode' => $this->langcode,
        'initiative_name' => $expression['name'],
        'description' => $this->buildFormattedText($expression, 'description'),
        'documented_in' => $expression['documented_in'] ?? NULL,
        'status' => TRUE,
      ]);
    }, $expressions);
  }

  /**
   * Creates a list of public service entities.
   *
   * @param array $affects
   *   List of public service definitions.
   * @param string $path
   *   The parent element path.
   *
   * @return list<\Drupal\assessments\AssessmentInterface>
   *   A list of public service entities.
   */
  protected function createPublicServices(array $affects, string $path): array {
    $services = [];

    foreach ($affects as $delta => $item) {
      $services[] = $this->getAssessmentStorage()->create([
        'type' => 'public_service',
        'langcode' => $this->langcode,
        'name' => $item['name'],
        'thematic_area' => isset($item['thematic_area']) ? $this->getTermIdByName('assess_public_service_area', $item['thematic_area'], "$path/affects/$delta/thematic_area") : [],
        'countries' => isset($item['country']) ? $this->getTermIdByName('spatial', $item['country'], "$path/affects/$delta/country", 'code') : [],
        'status' => TRUE,
      ]);
    }

    return $services;
  }

  /**
   * Creates a list of participation entities.
   *
   * @param array $hasParticipation
   *   List of participation definitions.
   * @param string $path
   *   The parent element path.
   *
   * @return list<\Drupal\assessments\AssessmentInterface>
   *   A list of participation entities.
   */
  protected function createParticipation(array $hasParticipation, string $path): array {
    $participation = [];

    foreach ($hasParticipation as $delta => $item) {
      $participation[] = $this->getAssessmentStorage()->create([
        'type' => 'participation',
        'langcode' => $this->langcode,
        'participant_type' => $this->getTermIdByName('assess_participant_type', $item['participant_type'], "$path/has_participation/$delta/participant_type"),
        'stakeholder_country' => isset($item['country']) ? $this->getTermIdByName('spatial', $item['country'], "$path/has_participation/$delta/country", 'code') : [],
        'status' => TRUE,
      ]);
    }

    return $participation;
  }

  /**
   * Returns and, if necessary, creates a list of solution entities.
   *
   * @param array $assets
   *   List of, either existing solution UUIDs, or solution definitions.
   * @param string $path
   *   The parent element path.
   *
   * @return list<\Drupal\joinup_solution\Entity\SolutionInterface>
   *   A list of solution entities.
   */
  protected function getAssets(array $assets, string $path): array {
    $storage = $this->entityTypeManager->getStorage('node');

    $solutions = [];
    foreach ($assets as $delta => $asset) {
      if (is_string($asset)) {
        $solution = $this->entityRepository->loadEntityByUuid('node', $asset);
        if (!$solution) {
          $this->addError("$path/asset/$delta", "An asset (interoperability solution) with ID '$asset' does not exist");
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
        'field_state' => 'published',
      ]);
    }

    return $solutions;
  }

  /**
   * Uploads the document files to the report.
   *
   * This is a big time-consuming process when sequential HTTP requests over
   * internet are involved, and could end with the main connection timing out.
   * For this reason, make use of the Guzzle concurrent requests feature.
   *
   * The requests are keyed by string key, which encapsulates useful information
   * to be used by the "success" callback.
   *
   * @param \Drupal\assessments\Entity\AssessmentReport $report
   *   The report entity.
   * @param array $data
   *   The payload.
   *
   * @see https://docs.guzzlephp.org/en/stable/quickstart.html#concurrent-requests
   */
  protected function uploadDocuments(AssessmentReport $report, array $data): void {
    $requests = function (array $data) {
      foreach ($data['documents'] ?? [] as $i => $url) {
        $key = "/documents/$i:assessment.report.documents:$url";
        yield $key => fn() => $this->httpClient->getAsync($url);
      }
      foreach ($data['binding_requirement'] as $i => $requirement) {
        foreach ($requirement['documents'] ?? [] as $j => $url) {
          $key = "/binding_requirement/$i/documents/$j:paragraph.binding_requirement.requirement_documents:$url";
          yield $key => fn() => $this->httpClient->getAsync($url);
        }
      }
    };

    $pool = new Pool($this->httpClient, $requests($data), [
      'concurrency' => 10,
      'fulfilled' => function (Response $response, string $key) use ($report): void {
        $this->uploadFile($response, $key, $report);
      },
      'rejected' => function (ClientExceptionInterface $reason, string $key): void {
        [$path] = explode(':', $key, 2);
        if ($reason instanceof ClientException) {
          // Use a more simple but meaningful message for client exceptions.
          $this->addError($path, "Error downloading file: {$reason->getCode()} {$reason->getResponse()->getReasonPhrase()}");
          return;
        }
        $this->addError($path, "Error downloading file: {$reason->getMessage()}");
      },
    ]);

    $promise = $pool->promise();
    $promise->wait();
  }

  /**
   * Uploads the files after they were successfully downloaded from remote.
   *
   * @param \GuzzleHttp\Psr7\Response $response
   *   The response received by the asynchronous GET request.
   * @param string $key
   *   The request key. We've packed in this string useful context information.
   * @param \Drupal\assessments\Entity\AssessmentReport $report
   *   The assessment report entity being created.
   */
  protected function uploadFile(Response $response, string $key, AssessmentReport $report): void {
    // Unpack context information from the request key.
    [$path, $fieldInfo, $url] = explode(':', $key, 3);
    [$entityTypeId, $bundle, $fieldName] = explode('.', $fieldInfo);

    // Prepare the destination directory.
    $definition = $this->fieldManager->getFieldDefinitions($entityTypeId, $bundle)[$fieldName];
    $destination = $this->getUploadLocation($definition);
    if (!$this->fileSystem->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY)) {
      throw new HttpException(500, 'Destination file path is not writable');
    }

    try {
      // Use the file upload validators, configured from the field settings.
      $settings = $definition->getSettings();
      $validators = $this->getFileUploadValidators($settings);

      $filename = basename(parse_url($url, PHP_URL_PATH));
      $tempPath = $this->fileSystem->tempnam('temporary://', 'file');
      file_put_contents($tempPath, $response->getBody()->getContents());
      $uploadedFile = new InputStreamUploadedFile($filename, $filename, $tempPath, @filesize($tempPath));

      $result = $this->uploadHandler->handleFileUpload($uploadedFile, $validators, $destination, FileExists::Rename, FALSE);
    }
    catch (LockAcquiringException $e) {
      throw new HttpException(503, $e->getMessage(), NULL, ['Retry-After' => 1]);
    }
    catch (UploadException $e) {
      $this->logger->error('Input data could not be read');
      throw new HttpException(500, 'Input file data could not be read', $e);
    }
    catch (CannotWriteFileException $e) {
      $this->logger->error('Temporary file data for could not be written');
      throw new HttpException(500, 'Temporary file data could not be written', $e);
    }
    catch (NoFileException $e) {
      $this->logger->error('Temporary file could not be opened for file upload');
      throw new HttpException(500, 'Temporary file could not be opened', $e);
    }
    catch (FileExistsException $e) {
      throw new HttpException(statusCode: 500, message: $e->getMessage(), previous: $e);
    }
    catch (FileException) {
      throw new HttpException(500, 'Temporary file could not be moved to file location');
    }

    if ($result->hasViolations()) {
      foreach ($result->getViolations() as $violation) {
        $this->addError($path, "Error saving file $filename: " . PlainTextOutput::renderFromHtml($violation->getMessage()));
      }
      return;
    }

    $file = $result->getFile();

    // Attach the file entity to the report.
    // @todo Find a more elegant way.
    match($entityTypeId) {
      'assessment' => $report->get('documents')->appendItem($file),
      'paragraph' => $report->get('binding_requirement')->entity->get('requirement_documents')->appendItem($file),
    };
  }

  /**
   * Converts terms identifiers to their corresponding term ID.
   *
   * @param string $vid
   *   The vocabulary ID.
   * @param array|string $identifier
   *   One or many term identifiers.
   *
   * @returns list<string|int>
   *   A list of term IDs.
   */
  protected function getTermIdByName(string $vid, array|string $identifier): array {
    return $this->assessmentVocabularyHelper->getTermIdFromIdentifier($vid, $identifier);
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
   *   when $itemList is an entity reference field and represents the field
   *   whose value to be retrieved from the target entity. It has no effect on
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
   * Returns a list of file URLs out of a file item list.
   *
   * @param \Drupal\file\Plugin\Field\FieldType\FileFieldItemList $itemList
   *   The file item list.
   *
   * @return list<string>
   *   A list of file URLs.
   */
  protected function fileUrls(FileFieldItemList $itemList): array {
    $urls = [];

    foreach ($itemList as $item) {
      $file = $item->entity;
      assert($file instanceof FileInterface);
      $urls[] = Url::fromUserInput($file->get('uri')->url)
        ->setAbsolute()
        ->toString();
    }

    return $urls;
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

  /**
   * Adds an error message to the stack.
   *
   * @param string $path
   *   The path to the payload property.
   * @param string $message
   *   The error message.
   */
  protected function addError(string $path, string $message): void {
    $this->errors[$path][] = $message;
    $this->errors[$path] = array_unique($this->errors[$path]);
  }

}
