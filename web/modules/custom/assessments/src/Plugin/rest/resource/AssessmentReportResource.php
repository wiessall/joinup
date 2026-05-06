<?php

declare(strict_types=1);

namespace Drupal\assessments\Plugin\rest\resource;

use Drupal\Component\Render\PlainTextOutput;
use Drupal\Component\Utility\Bytes;
use Drupal\Component\Utility\Environment;
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
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Lock\LockAcquiringException;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\assessments\AssessmentInterface;
use Drupal\assessments\AssessmentVocabularyHelper;
use Drupal\assessments\Entity\AssessmentReport;
use Drupal\assessments\Entity\AssessmentStatement;
use Drupal\assessments\Entity\PublicOrganisation;
use Drupal\assessments\ErrorMessage;
use Drupal\assessments\Http\CappedStream;
use Drupal\assessments\Http\MaxDownloadSizeExceededException;
use Drupal\assessments\ProvidedByHelperTrait;
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
use GuzzleHttp\Psr7\Utils;
use GuzzleHttp\RequestOptions;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\File\Exception\CannotWriteFileException;
use Symfony\Component\HttpFoundation\File\Exception\NoFileException;
use Symfony\Component\HttpFoundation\File\Exception\UploadException;
use Symfony\Component\HttpFoundation\RequestStack;
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
  use ProvidedByHelperTrait;

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
    protected readonly FileUrlGeneratorInterface $fileUrlGenerator,
    protected readonly RequestStack $requestStack,
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
      $container->get('file_url_generator'),
      $container->get('request_stack'),
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
      return new ResourceResponse([
        'errors' => $this->prepareErrorMessages($errors, $data),
      ], 422);
    }

    $report = $this->createReport($data);

    if ($this->errors) {
      // Files that downloaded successfully are saved as managed file entities
      // by the upload handler. When the overall request fails (provider/asset
      // errors, cardinality, etc.) the report is never saved, so those files
      // would orphan on disk. Delete them before returning the 422.
      $this->deleteOrphanFiles($report);
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
        'documented_in' => $requirement->get('documented_in')->uri,
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
        if ($interpretation = $this->itemListToValue($item->get('interpretation'), 'id')) {
          $resultsIn[$type] = [
            'interpretation' => $interpretation,
            'judgement' => $item->get('judgement')->value,
          ];
        }
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
    // Support of report langcode on creation was removed in ISAICP-11003
    // temporarily.
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
    // Try to find an existing organisation matching the given criteria.
    $existingOrganisation = $this->findExistingPublicOrganisation($provider);
    if ($existingOrganisation) {
      return $existingOrganisation;
    }

    if (is_string($provider)) {
      // UUID lookup failed in findExistingPublicOrganisation.
      return NULL;
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
   * Finds an existing public organisation matching the given provider data.
   *
   * @param array|string $provider
   *   Either the public organisation data, or an existing entity UUID.
   *
   * @return \Drupal\assessments\Entity\PublicOrganisation|null
   *   The matching public organisation, or NULL if not found.
   */
  protected function findExistingPublicOrganisation(array|string $provider): ?PublicOrganisation {
    // If provider is a UUID, try to load by UUID.
    if (is_string($provider)) {
      $organisation = $this->entityRepository->loadEntityByUuid('assessment', $provider);
      if (!$organisation) {
        $this->addError('/provider', "A public organisation with ID '$provider' does not exist");
        return NULL;
      }
      return $organisation instanceof PublicOrganisation ? $organisation : NULL;
    }

    $organisationTypeIds = $this->getTermIdByName('assess_provider_type', $provider['organisation_type'], "/provider/organisation_type");
    if (empty($organisationTypeIds)) {
      return NULL;
    }

    $isEuropean = $this->isEuropeanOrganisationType($provider['organisation_type']);

    // Build the query to find organisations of the given type.
    $query = $this->getAssessmentStorage()->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'public_organisation')
      ->condition('organisation_type', $organisationTypeIds, 'IN');

    // For non-European types, also filter by country.
    if (!$isEuropean && isset($provider['country'])) {
      $countryIds = $this->getTermIdByName('spatial', $provider['country'], "/provider/country", 'code');
      if (!empty($countryIds)) {
        $query->condition('country', $countryIds, 'IN');
      }
    }

    $ids = $query->execute();

    return $this->findOrganisationByName($provider['name'], $ids);
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
        'documented_in' => $requirement['documented_in'] ?? NULL,
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
        else {
          if ($solution->get('field_state')->value === 'published' && $solution->isPublished()) {
            $solutions[] = $solution;
          }
          else {
            $this->addError("$path/asset/$delta", "The asset (interoperability solution) with ID '$asset' is invalid");
          }
        }
        continue;
      }

      $solution = $storage->create([
        'type' => 'solution',
        'langcode' => $this->langcode,
        'og_audience' => $this->entityRepository->loadEntityByUuid('node', AssessmentInterface::SOLUTION_COLLECTOR),
        'title' => $asset['title'],
        'description' => $this->buildFormattedText($asset, 'description', 'content_editor'),
        'landing_page' => $asset['landing_page'],
        'field_state' => 'published',
      ]);

      foreach ($solution->validate()->getByField('title') as $violation) {
        $this->addError("$path/asset/$delta/title", $violation->getMessage()->__toString());
      }

      $solutions[] = $solution;
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
    $safeRequestOptions = [
      RequestOptions::TIMEOUT => 30,
      RequestOptions::CONNECT_TIMEOUT => 10,
      // Redirects are disabled: a redirect target cannot be re-validated for
      // private/reserved IPs, which would re-open the SSRF window.
      RequestOptions::ALLOW_REDIRECTS => FALSE,
    ];

    // Collect all documents across the payload, applying the per-field
    // cardinality cap up-front. Anything beyond the cap is rejected with a
    // payload-path error rather than fetched.
    $items = $this->collectDocumentItems($data);

    // Per-key map of sink temp file paths, populated during request iteration
    // and consumed by the fulfilled/rejected handlers so they can find the
    // streamed download (or clean it up on failure).
    $tempPaths = [];

    $requests = function (array $items) use ($safeRequestOptions, $report, &$tempPaths) {
      foreach ($items as [$path, $fieldInfo, $url]) {
        if (!$this->validateDocumentUrl($url, $path)) {
          continue;
        }
        if (!$this->validateDocumentExtension($url, $fieldInfo, $path)) {
          continue;
        }
        $key = "$path:$fieldInfo:$url";
        if ($localUri = $this->resolveLocalPath($url)) {
          $this->uploadLocalFile($localUri, $key, $report);
          continue;
        }
        // Own-site URL pointing at the public files prefix but the file does
        // not exist (or fails the safety checks). Surface a clean error
        // rather than chasing it through HTTP, where the response (302/404)
        // varies per web-server config and would be non-deterministic.
        if ($this->isOwnSitePublicUrl($url)) {
          $this->addError($path, 'Document URL points to a missing file');
          continue;
        }
        // Per-request capped sink: streams the body to a temp file and aborts
        // the transfer once the field's max_filesize is exceeded.
        $tempPath = $this->fileSystem->tempnam('temporary://', 'doc_');
        $tempPaths[$key] = $tempPath;
        $maxBytes = $this->getMaxFilesizeForFieldInfo($fieldInfo);
        $sinkResource = fopen($this->fileSystem->realpath($tempPath), 'wb');
        $sink = new CappedStream(Utils::streamFor($sinkResource), $maxBytes);
        yield $key => fn() => $this->httpClient->getAsync($url, [
          ...$safeRequestOptions,
          RequestOptions::SINK => $sink,
        ]);
      }
    };

    $pool = new Pool($this->httpClient, $requests($items), [
      'concurrency' => 10,
      'fulfilled' => function (Response $response, string $key) use ($report, &$tempPaths): void {
        $tempPath = $tempPaths[$key] ?? NULL;
        unset($tempPaths[$key]);
        // Redirects are disabled (SSRF-safety); treat any non-2xx response as
        // an error so a 3xx body doesn't get attached as the document itself.
        if ($response->getStatusCode() >= 300) {
          if ($tempPath !== NULL) {
            try {
              $this->fileSystem->unlink($tempPath);
            }
            catch (\Throwable) {
            }
          }
          [$path] = explode(':', $key, 2);
          $this->addError($path, "Error downloading file: {$response->getStatusCode()} {$response->getReasonPhrase()}");
          return;
        }
        $this->uploadFile($response, $key, $report, $tempPath);
      },
      'rejected' => function (\Throwable $reason, string $key) use (&$tempPaths): void {
        [$path] = explode(':', $key, 2);
        if (isset($tempPaths[$key])) {
          try {
            $this->fileSystem->unlink($tempPaths[$key]);
          }
          catch (\Throwable) {
            // Best-effort cleanup; the temp dir is purged periodically.
          }
          unset($tempPaths[$key]);
        }
        if ($reason instanceof MaxDownloadSizeExceededException) {
          $this->addError($path, $reason->getMessage());
          return;
        }
        if ($reason instanceof ClientException) {
          // Use a more simple but meaningful message for client exceptions.
          $this->addError($path, "Error downloading file: {$reason->getCode()} {$reason->getResponse()->getReasonPhrase()}");
          return;
        }
        $this->addError($path, "Error downloading file: {$reason->getMessage()}");
      },
    ]);

    $pool->promise()->wait();
  }

  /**
   * Collects flat document items from the payload, enforcing cardinality.
   *
   * @return list<array{0:string,1:string,2:string}>
   *   List of [payload_path, field_info, url] tuples.
   */
  protected function collectDocumentItems(array $data): array {
    $items = [];

    $documentsCap = $this->getCardinalityForFieldInfo('assessment.report.documents');
    foreach ($data['documents'] ?? [] as $i => $url) {
      if ($documentsCap !== -1 && $i >= $documentsCap) {
        $this->addError("/documents/$i", "Exceeds field cardinality (max $documentsCap documents)");
        continue;
      }
      $items[] = ["/documents/$i", 'assessment.report.documents', $url];
    }

    $reqCap = $this->getCardinalityForFieldInfo('paragraph.binding_requirement.requirement_documents');
    foreach ($data['binding_requirement'] ?? [] as $i => $requirement) {
      foreach ($requirement['documents'] ?? [] as $j => $url) {
        if ($reqCap !== -1 && $j >= $reqCap) {
          $this->addError("/binding_requirement/$i/documents/$j", "Exceeds field cardinality (max $reqCap documents)");
          continue;
        }
        $items[] = ["/binding_requirement/$i/documents/$j", 'paragraph.binding_requirement.requirement_documents', $url];
      }
    }

    return $items;
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
   * @param string|null $tempPath
   *   The path to the temp file already populated by the streaming sink. When
   *   omitted (legacy callers), the response body is materialised here.
   */
  protected function uploadFile(Response $response, string $key, AssessmentReport $report, ?string $tempPath = NULL): void {
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
      if ($tempPath === NULL) {
        $tempPath = $this->fileSystem->tempnam('temporary://', 'file');
        file_put_contents($tempPath, $response->getBody()->getContents());
      }
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
    match($entityTypeId) {
      'assessment' => $report->get('documents')->appendItem($file),
      'paragraph' => $this->attachFileToParagraph($report, $path, $fieldName, $file),
    };
  }

  /**
   * Attaches a file to the correct binding requirement paragraph.
   *
   * @param \Drupal\assessments\Entity\AssessmentReport $report
   *   The assessment report entity.
   * @param string $path
   *   The path from the payload (e.g., "/binding_requirement/1/documents/0").
   * @param string $fieldName
   *   The field name to attach the file to.
   * @param \Drupal\file\FileInterface $file
   *   The file entity to attach.
   */
  protected function attachFileToParagraph(AssessmentReport $report, string $path, string $fieldName, FileInterface $file): void {
    // Extract the binding requirement delta from the path.
    // Path format: /binding_requirement/{delta}/documents/{docIndex}.
    if (preg_match('#^/binding_requirement/(\d+)/#', $path, $matches)) {
      $delta = (int) $matches[1];
      $bindingRequirements = $report->get('binding_requirement');
      if (isset($bindingRequirements[$delta])) {
        $bindingRequirements[$delta]->entity->get($fieldName)->appendItem($file);
      }
    }
  }

  /**
   * Validates a document URL against SSRF-unsafe schemes and IP ranges.
   *
   * Only http/https are allowed. The hostname is resolved to its IP addresses
   * and any private, loopback, or link-local address is rejected to prevent
   * Server-Side Request Forgery.
   *
   * @param string $url
   *   The URL to validate.
   * @param string $path
   *   The payload path used for error reporting.
   *
   * @return bool
   *   TRUE if the URL is safe to fetch, FALSE otherwise (error is recorded).
   */
  protected function validateDocumentUrl(string $url, string $path): bool {
    $parsed = parse_url($url);

    if (empty($parsed['scheme']) || !in_array($parsed['scheme'], ['http', 'https'], TRUE)) {
      $this->addError($path, 'Document URL must use the http or https scheme');
      return FALSE;
    }

    $host = $parsed['host'] ?? '';
    if ($host === '') {
      $this->addError($path, 'Document URL has no host');
      return FALSE;
    }

    // Own-site URLs bypass the private/reserved IP check. Inside containers
    // the site's hostname commonly resolves to RFC1918/loopback, which the IP
    // filter would otherwise reject for legitimate references back to our own
    // files. Local files are short-circuited to the filesystem in
    // uploadDocuments(); any non-file own-site URL still goes through Guzzle.
    if ($this->isOwnSiteHost($host)) {
      return TRUE;
    }

    // Strip IPv6 bracket notation before lookup.
    $lookupHost = trim($host, '[]');

    $ips = $this->resolveDocumentHost($lookupHost);
    if (empty($ips)) {
      $this->addError($path, 'Could not resolve document URL host');
      return FALSE;
    }

    foreach ($ips as $ip) {
      // FILTER_FLAG_NO_PRIV_RANGE blocks RFC1918 IPv4 and IPv6 ULA/link-local.
      // FILTER_FLAG_NO_RES_RANGE blocks loopback, 169.254.x.x, and reserved.
      if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        $this->addError($path, 'Document URL must not point to a private or reserved IP address');
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Resolves a hostname to all its IPv4 and IPv6 addresses.
   *
   * @param string $host
   *   A hostname or bare IP address (without brackets).
   *
   * @return list<string>
   *   Resolved IP addresses, or the input itself when it is already an IP.
   */
  protected function resolveDocumentHost(string $host): array {
    if (filter_var($host, FILTER_VALIDATE_IP)) {
      return [$host];
    }

    $ips = gethostbynamel($host) ?: [];

    $records = @dns_get_record($host, DNS_AAAA) ?: [];
    foreach ($records as $record) {
      if (isset($record['ipv6'])) {
        $ips[] = $record['ipv6'];
      }
    }

    return $ips;
  }

  /**
   * Returns the cardinality of the field referred to by a packed fieldInfo.
   */
  protected function getCardinalityForFieldInfo(string $fieldInfo): int {
    [$entityTypeId, , $fieldName] = explode('.', $fieldInfo);
    $storage = $this->fieldManager->getFieldStorageDefinitions($entityTypeId)[$fieldName] ?? NULL;
    return $storage ? $storage->getCardinality() : -1;
  }

  /**
   * Returns the per-file max byte cap configured on the target field.
   *
   * Falls back to the system upload ceiling
   * (min(upload_max_filesize, post_max_size)) when the field has no explicit
   * setting, mirroring how Drupal's own file widgets behave.
   */
  protected function getMaxFilesizeForFieldInfo(string $fieldInfo): int {
    [$entityTypeId, $bundle, $fieldName] = explode('.', $fieldInfo);
    $definition = $this->fieldManager->getFieldDefinitions($entityTypeId, $bundle)[$fieldName] ?? NULL;
    $configured = $definition ? (string) $definition->getSetting('max_filesize') : '';
    if ($configured !== '') {
      return (int) Bytes::toNumber($configured);
    }
    return (int) Environment::getUploadMaxSize();
  }

  /**
   * Validates the URL's filename extension against the field's allowlist.
   *
   * The remote response is also re-validated against the same allowlist after
   * download, but checking the URL up-front lets us reject obviously invalid
   * extensions before spending bandwidth on the fetch.
   */
  protected function validateDocumentExtension(string $url, string $fieldInfo, string $path): bool {
    [$entityTypeId, $bundle, $fieldName] = explode('.', $fieldInfo);
    $definition = $this->fieldManager->getFieldDefinitions($entityTypeId, $bundle)[$fieldName] ?? NULL;
    $rawExtensions = $definition ? (string) $definition->getSetting('file_extensions') : '';
    $allowed = preg_split('/\s+/', $rawExtensions, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if (empty($allowed)) {
      return TRUE;
    }
    $urlPath = parse_url($url, PHP_URL_PATH) ?? '';
    $ext = strtolower(pathinfo($urlPath, PATHINFO_EXTENSION));
    $allowedLower = array_map('strtolower', $allowed);
    if ($ext === '' || !in_array($ext, $allowedLower, TRUE)) {
      $this->addError($path, "Document URL extension '$ext' is not allowed (expected: " . implode(', ', $allowed) . ')');
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Whether the host belongs to this site.
   */
  protected function isOwnSiteHost(string $host): bool {
    $request = $this->requestStack->getCurrentRequest();
    $candidates = array_filter([
      $request?->getHost(),
      parse_url((string) $this->fileUrlGenerator->generateAbsoluteString('public://'), PHP_URL_HOST),
    ]);
    $host = strtolower(trim($host, '[]'));
    return in_array($host, array_map('strtolower', $candidates), TRUE);
  }

  /**
   * Whether the URL is an own-site URL pointing at the public files prefix.
   *
   * Used to short-circuit URLs that look like our own public files but cannot
   * be resolved to an existing file: making an HTTP call in that case yields
   * a response (302/404/etc.) that varies per web-server config.
   */
  protected function isOwnSitePublicUrl(string $url): bool {
    $parsed = parse_url($url);
    if (empty($parsed['path']) || empty($parsed['host']) || !$this->isOwnSiteHost($parsed['host'])) {
      return FALSE;
    }
    $publicAbs = (string) $this->fileUrlGenerator->generateAbsoluteString('public://');
    $publicPrefix = parse_url($publicAbs, PHP_URL_PATH) ?? '';
    return $publicPrefix !== '' && str_starts_with($parsed['path'], $publicPrefix);
  }

  /**
   * Maps an own-site URL to a public:// stream URI, or NULL if it doesn't map.
   *
   * Only files under public:// are eligible. Path traversal is blocked by
   * comparing the resolved real path to the public files real path.
   */
  protected function resolveLocalPath(string $url): ?string {
    $parsed = parse_url($url);
    if (empty($parsed['path']) || empty($parsed['host']) || !$this->isOwnSiteHost($parsed['host'])) {
      return NULL;
    }
    $publicAbs = (string) $this->fileUrlGenerator->generateAbsoluteString('public://');
    $publicPrefix = parse_url($publicAbs, PHP_URL_PATH) ?? '';
    if ($publicPrefix === '' || !str_starts_with($parsed['path'], $publicPrefix)) {
      return NULL;
    }
    $relative = ltrim(rawurldecode(substr($parsed['path'], strlen($publicPrefix))), '/');
    if ($relative === '') {
      return NULL;
    }
    $candidate = 'public://' . $relative;
    $realPublic = realpath($this->fileSystem->realpath('public://'));
    $realFile = realpath($this->fileSystem->realpath($candidate));
    if ($realPublic === FALSE || $realFile === FALSE) {
      return NULL;
    }
    if (!str_starts_with($realFile . DIRECTORY_SEPARATOR, $realPublic . DIRECTORY_SEPARATOR)) {
      return NULL;
    }
    return is_file($realFile) ? $candidate : NULL;
  }

  /**
   * Copies an own-site file into the report, mirroring uploadFile().
   *
   * @param string $localUri
   *   A public:// stream URI verified by resolveLocalPath().
   * @param string $key
   *   The packed request key: "$path:$entityType.$bundle.$fieldName:$url".
   * @param \Drupal\assessments\Entity\AssessmentReport $report
   *   The assessment report entity being created.
   */
  protected function uploadLocalFile(string $localUri, string $key, AssessmentReport $report): void {
    [$path, $fieldInfo, $url] = explode(':', $key, 3);
    [$entityTypeId, $bundle, $fieldName] = explode('.', $fieldInfo);

    $definition = $this->fieldManager->getFieldDefinitions($entityTypeId, $bundle)[$fieldName];
    $destination = $this->getUploadLocation($definition);
    if (!$this->fileSystem->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY)) {
      throw new HttpException(500, 'Destination file path is not writable');
    }

    try {
      $validators = $this->getFileUploadValidators($definition->getSettings());
      $filename = basename(parse_url($url, PHP_URL_PATH));
      $tempPath = $this->fileSystem->tempnam('temporary://', 'file');
      // copy() handles stream wrappers; size and extension are checked by the
      // file upload validators below, the same as for remote downloads.
      $this->fileSystem->copy($localUri, $tempPath, FileExists::Replace);
      $uploadedFile = new InputStreamUploadedFile($filename, $filename, $tempPath, @filesize($tempPath));
      $result = $this->uploadHandler->handleFileUpload($uploadedFile, $validators, $destination, FileExists::Rename, FALSE);
    }
    catch (LockAcquiringException $e) {
      throw new HttpException(503, $e->getMessage(), NULL, ['Retry-After' => 1]);
    }
    catch (FileException $e) {
      throw new HttpException(500, 'Local file could not be copied', $e);
    }

    if ($result->hasViolations()) {
      foreach ($result->getViolations() as $violation) {
        $this->addError($path, "Error saving file $filename: " . PlainTextOutput::renderFromHtml($violation->getMessage()));
      }
      return;
    }

    $file = $result->getFile();
    match ($entityTypeId) {
      'assessment' => $report->get('documents')->appendItem($file),
      'paragraph' => $this->attachFileToParagraph($report, $path, $fieldName, $file),
    };
  }

  /**
   * Converts terms identifiers to their corresponding term ID.
   *
   * @param string $vid
   *   The vocabulary ID.
   * @param array|string $identifier
   *   One or many term identifiers.
   * @param string $path
   *   The path.
   * @param string $field
   *   The field.
   *
   * @returns list<string|int>
   *   A list of term IDs.
   */
  protected function getTermIdByName(string $vid, array|string $identifier, string $path, string $field = 'id'): array {
    $identifiers = (array) $identifier;
    $tids = [];
    foreach ($identifiers as $identifier) {
      try {
        $tids[] = $this->assessmentVocabularyHelper->getTermIdFromIdentifier($vid, $identifier, $field);
      }
      catch (\Exception $e) {
        $this->addError($path, $e->getMessage());
      }
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

    return $cardinality == 1 ? ($items ? reset($items) : NULL) : $items;
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
   * {@inheritdoc}
   */
  protected function getProvidedByAssessmentStorage(): ContentEntityStorageInterface {
    return $this->getAssessmentStorage();
  }

  /**
   * Deletes file entities attached to an unsaved report and its paragraphs.
   */
  protected function deleteOrphanFiles(AssessmentReport $report): void {
    foreach ($report->get('documents') as $item) {
      if ($item->entity instanceof FileInterface) {
        $item->entity->delete();
      }
    }
    foreach ($report->get('binding_requirement') as $requirementItem) {
      $paragraph = $requirementItem->entity;
      if (!$paragraph instanceof ParagraphInterface) {
        continue;
      }
      foreach ($paragraph->get('requirement_documents') as $item) {
        if ($item->entity instanceof FileInterface) {
          $item->entity->delete();
        }
      }
    }
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

  /**
   * Prepares a collection of error messages based on the provided input.
   *
   * Validation errors are sometimes too cryptic to be useful for the consumer.
   * This method tries to enhance them by providing more meaningful messages.
   *
   * A typical case is the assessments API we often assign two alternative
   * schemas to a property by using the oneOf operator. The practical case is
   * when we need EITHER to create an object OR refer it by its ID. When we pass
   * an object which doesn't validate, the following sequence or error messages
   * is generated:
   * - Object value found, but a string is required
   * - Failed to match exactly one schema
   * The same happens when the string is found but is invalid. But this looks
   * quite misleading for the consumer. We enhance it by replacing both
   * messages with one more meaningful.
   *
   * @param array $errorsByPath
   *   An array containing validation errors for specific fields.
   * @param array $data
   *   An array containing the original data related to the errors.
   *
   * @return array
   *   An array of processed error messages.
   */
  protected function prepareErrorMessages(array $errorsByPath, array $data): array {
    foreach ($errorsByPath as &$errors) {
      // Key by error message as is easier to handle. We'll revert later.
      $errors = array_combine($errors, $errors);

      if (!isset($errors[ErrorMessage::OneOf->value])) {
        continue;
      }
      unset($errors[ErrorMessage::OneOf->value]);

      if (isset($errors[ErrorMessage::ObjectFoundStringRequired->value])) {
        unset($errors[ErrorMessage::ObjectFoundStringRequired->value]);
        $errors[ErrorMessage::ObjectFoundStringRequiredAlt->value] = ErrorMessage::ObjectFoundStringRequiredAlt->value;
      }

      if (isset($errors[ErrorMessage::StringFoundObjectRequired->value])) {
        unset($errors[ErrorMessage::StringFoundObjectRequired->value]);
        $errors[ErrorMessage::StringFoundObjectRequiredAlt->value] = ErrorMessage::StringFoundObjectRequiredAlt->value;
      }
    }

    return array_map(
      fn(array $errors): array => array_values($errors),
      $errorsByPath,
    );
  }

}
