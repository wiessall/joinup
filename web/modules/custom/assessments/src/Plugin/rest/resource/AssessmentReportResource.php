<?php

declare(strict_types=1);

namespace Drupal\assessments\Plugin\rest\resource;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\assessments\ResourceParserInterface;
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
    protected ResourceParserInterface $resourceParser,
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
      $container->get(ResourceParserInterface::class)
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
    // Basic validation: ensure we got a single object with a name field as
    // per schema.
    if (!is_array($data) || (isset($data[0]) && is_array($data[0]))) {
      return new ResourceResponse(['error' => 'Payload must be a single JSON object.'], 400);
    }

    $parsed = $this->resourceParser->parseInbound($data);
    if (!empty($parsed['errors'])) {
      // Return an unprocessable entity response with the errors.
      return new ResourceResponse(['errors' => $parsed['errors']], 422);
    }

    $response = new ResourceResponse($parsed['data'], 201);
    $response->addCacheableDependency((new CacheableMetadata())->setCacheMaxAge(0));
    return $response;
  }

}
