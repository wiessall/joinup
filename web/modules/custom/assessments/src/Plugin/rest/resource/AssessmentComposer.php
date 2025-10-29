<?php

declare(strict_types=1);

namespace Drupal\assessments\Plugin\rest\resource;

use Drupal\assessments\Entity\Assessment;
use Drupal\assessments\Entity\AssessmentContent;
use Drupal\assessments\Entity\Asset;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\rest\Attribute\RestResource;
use Drupal\rest\ResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a REST resource to consume Assessment composer payloads.
 */
#[RestResource(
  id: "assessments_composer",
  label: new TranslatableMarkup("Assessment composer"),
  uri_paths: [
    "canonical" => "/api/assessments-composer/{assessment_composer}",
    "create" => "/api/assessments-composer",
  ],
)]
class AssessmentComposer extends ResourceBase {

  /**
   * An array of errors encountered during processing.
   *
   * This is used to return a 400 response with the errors. The structure is:
   * - path: The path to the field that caused the error.
   * - message: The error message.
   *
   * @var array{string, string}[]
   */
  protected array $errors = [];

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    protected AccountProxyInterface $currentUser,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
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
      $container->get('current_user'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Responds to a GET request.
   *
   * @param string $assessment_composer
   *   The assessment composer name.
   *
   * @return \Drupal\rest\ResourceResponse
   *   A 404 response if the assessment composer does not exist or the
   *  assessment array.
   */
  public function get(string $assessment_composer): ResourceResponse {
    $assessment = $this->entityTypeManager->getStorage('assessment_content')->loadByProperties(['uuid' => $assessment_composer]);
    if (!$assessment) {
      return new ResourceResponse(['error' => 'Assessment composer not found.'], 404);
    }
    return new ResourceResponse($assessment);
  }

  /**
   * Responds to POST requests.
   *
   * Accepts a single JSON object representing the Assessment and related
   * entities. Currently, this is a pass-through that echoes the received data
   * with a 201 status. Integrate your persistence logic where noted.
   *
   * @param array $data
   *   The decoded JSON payload.
   *
   * @return \Drupal\rest\ResourceResponse
   */
  public function post(array $data): ResourceResponse {
    // Basic validation: ensure we got a single object with a name field as per schema.
    if (!is_array($data) || (isset($data[0]) && is_array($data[0]))) {
      return new ResourceResponse(['error' => 'Payload must be a single JSON object.'], 400);
    }

    // Extract each class from the payload into variables. No persistence yet.
    // Top-level Assessment fields
    $assessment = [
      'name' => $data['name'],
    ];

    // Helper to normalize to array for fields that can be single value or array.
    $asList = static function ($value) {
      if ($value === NULL) {
        return [];
      }
      return is_array($value) && array_is_list($value) ? $value : [$value];
    };

    // binding_requirement: array of UUIDs or structured BindingRequirement objects
    $bindingRequirements = [];
    foreach ($asList($data['binding_requirement'] ?? []) as $br) {
      $bindingRequirements[] = [
        'name' => $br['name'],
        'documented_in' => $br['documented_in'],
      ];
      $bindingRequirements = $this->createEntities('binding_requirement', $bindingRequirements);
    }

    // provider: single UUID or Organisation object
    $provider = $data['provider'] ?? NULL;
    $providers = $this->createEntities('organisation', [$provider]);

    $statements = [];
    foreach ($asList($data['results_in'] ?? []) as $st) {
      $statements[] = [
        'interpretation' => $st['interpretation'],
        'judgement' => $st['judgement'],
        'statement_type' => $st['type'],
      ];
      $statements = $this->createEntities('statement', $statements);
    }

    // Collect nested classes inside binding_requirements
    $expressions = [];
    $publicServices = [];
    $participations = [];
    $assets = [];

    foreach ($asList($data['binding_requirement'] ?? []) as $br) {
      // Skip if $br is a UUID string.
      if (!is_array($br)) {
        continue;
      }

      // expression: list of UUIDs or Expression objects
      foreach ($asList($br['expression'] ?? []) as $expr) {
        $expressions[] = [
          'name' => $expr['name'],
          'description' => $expr['description'],
          'document_in' => $expr['document_in'],
          'expression_type' => $expr['expression_type'],
        ];
      }
      $expressions = $this->createEntities('expression', $expressions);

      // affects: list of UUIDs or PublicService objects
      foreach ($asList($br['affects'] ?? []) as $ps) {
        $publicServices[] = [
          'description' => $ps['description'],
          'identifier' => $ps['identifier'],
          'name' => $ps['name'],
          'thematic_area' => $ps['thematic_area'],
        ];
      }
      $publicServices = $this->createEntities('public_service', $publicServices);

      // has_participation: list of UUIDs or Participation objects
      foreach ($asList($br['has_participation'] ?? []) as $part) {
        $participations[] = [
          'country' => $part['country'],
          'participation_type' => $part['participation_type'],
        ];
      }
      $participations = $this->createEntities('participation', $participations);

      // asset: list of UUIDs or Asset objects
      foreach ($asList($br['asset'] ?? []) as $asset) {
        $assets[] = [
          'name' => $asset['name'],
          'landing_page' => $asset['landing_page'],
        ];
      }
      $assets = $this->createEntities('asset', $assets);
    }

    $assessmentReport = [
      'name' => $data['name'],
      'created' => REQUEST_TIME,
      'uid' => $this->currentUser->id(),
      'state' => 'under_review',
      'binding_requirement' => array_map(fn ($br) => $br->id(), $bindingRequirements),
      'affected_party' => array_map(fn ($ps) => $ps->id(), $participations),
      'has_related_service' => array_map(fn ($ps) => $ps->id(), $publicServices),
      'asset' => array_map(fn ($asset) => $asset->id(), $assets),
      'expression' => array_map(fn ($expr) => $expr->id(), $expressions),
      'provider' => array_map(fn ($org) => $org->id(), $providers),
      'results_in' => array_map(fn ($st) => $st->id(), $statements),
    ];
    $assessmentReport = $this->createEntities('assessment', [$assessmentReport]);

    $createdEntities = [
      'assessment' => $assessmentReport[0]->uuid(),
      'binding_requirements' => array_map(fn ($br) => $br->uuid(), $bindingRequirements),
      'providers' => array_map(fn ($org) => $org->uuid(), $providers),
      'statements' => array_map(fn ($st) => $st->uuid(), $statements),
      'expressions' => array_map(fn ($expr) => $expr->uuid(), $expressions),
      'public_services' => array_map(fn ($ps) => $ps->uuid(), $publicServices),
      'participations' => array_map(fn ($part) => $part->uuid(), $participations),
    ];

    $response = new ResourceResponse($createdEntities, 201);
    $response->addCacheableDependency((new CacheableMetadata())->setCacheMaxAge(0));
    return $response;
  }

  /**
   * Creates entities from the given entities data.
   *
   * @param string $bundle
   *   The bundle of the entities to create.
   * @param array $entitiesData
   *   The entities data to create assets from. Each index is an array of
   *   entity values.
   *
   * @return \Drupal\assessments\Entity\AssessmentContent[]
   *   The created entities.
   */
  protected function createEntities(string $bundle, array $entitiesData): array {
    $entities = [];
    foreach ($entitiesData as $entityData) {
      $entity = $this->entityTypeManager->getStorage('assessment_content')->create(
        ['type' => $bundle, 'created' => REQUEST_TIME, 'uid' => $this->currentUser->id()] + $entityData
      );
      $entity->save();
      $entities[] = $entity;
    }

    return $entities;
  }

  /**
   * Returns a new assessment report or loads one.
   *
   * @param string $uuid
   *   The name of the new assessment report.
   *
   * @return \Drupal\assessments\Entity\Assessment
   *   The assessment entity.
   */
  protected function getAssessmentReport(string $name): Assessment {
    return $this->entityTypeManager->getStorage('assessment_content')->create([
      'type' => 'assessment',
      'name' => $name,
      'created' => REQUEST_TIME,
      // @todo Load the request from the client and use the user's ID.
      'uid' => $this->currentUser->id(),
      'state' => 'under_review',
    ]);
  }

  /**
   * Adds an error entry to the error list.
   *
   * @param string $path
   *   The path associated with the error.
   * @param string $message
   *   The error message to be recorded.
   */
  protected function addError(string $path, string $message): void {
    $this->errors[] = [
      'path' => $path,
      'message' => $message,
    ];
  }

}

