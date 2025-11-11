<?php

declare(strict_types=1);

namespace Drupal\assessments\Plugin\rest\resource;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\assessments\Entity\Assessment;
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
  label: new TranslatableMarkup("Assessment report resource"),
  uri_paths: [
    "create" => "/api/assessments/v1/assessment/create",
  ],
)]
class AssessmentReportResource extends ResourceBase {

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
   * Responds to POST requests.
   *
   * Accepts a single JSON object representing the Assessment and related
   * entities. Currently, this is a pass-through that echoes the received data
   * with a 201 status. Integrate your persistence logic where noted.
   *
   * @param array $data
   *   The decoded JSON payload.
   */
  public function post(array $data): ResourceResponse {
    // Basic validation: ensure we got a single object with a name field as per schema.
    if (!is_array($data) || (isset($data[0]) && is_array($data[0]))) {
      return new ResourceResponse(['error' => 'Payload must be a single JSON object.'], 400);
    }

    $processor = \Drupal::service('assessments.resource_parser');
    $processor->parseInbound($data);
    $validationErrors = $processor->getEntityValidationErrors();

    $response = new ResourceResponse($validationErrors, 201);
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
   * @return \Drupal\assessments\Entity\Assessment[]
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
