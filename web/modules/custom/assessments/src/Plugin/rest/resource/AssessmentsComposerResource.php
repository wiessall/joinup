<?php

declare(strict_types=1);

namespace Drupal\assessments\Plugin\rest\resource;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Provides a REST Resource for composing assessments.
 *
 * @RestResource(
 *   id = "assessments:composer",
 *   label = @Translation("Assessments composer"),
 *   serialization_class = "Drupal\\Core\\Entity\\Entity",
 *   uri_paths = {
 *     "canonical" = "/api/assessments",
 *     "create" = "/api/assessments"
 *   }
 * )
 */
final class AssessmentsComposerResource extends ResourceBase {

  /**
   * Constructs a new AssessmentsComposerResource object.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccountProxyInterface $currentUser,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('assessments'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
    );
  }

  /**
   * Create a Public Organization from embedded data and return entity ID.
   */
  private function createPublicOrganization(array $data): ?int {
    if ($data === []) {
      return NULL;
    }
    $values = [
      'type' => 'public_organisation',
    ];
    if (isset($data['name'])) {
      $values['name'] = $data['name'];
    }
    if (isset($data['organization_type'])) {
      $values['organization_type'] = $data['organization_type'];
    }
    if (isset($data['country'])) {
      $values['country'] = $data['country'];
    }
    // Standard defaults.
    $values['uid'] = $this->currentUser->id() ?: 0;
    $values['created'] = time();

    $storage = $this->entityTypeManager->getStorage('assessment');
    // In this simplified model sub-entities are also assessment
    // bundles.
    $entity = $storage->create($values);
    $entity->save();
    return (int) $entity->id();
  }

  /**
   * Creates an expression entity and returns its ID.
   */
  private function createExpression(array $data): ?int {
    if ($data === []) {
      return NULL;
    }
    $values = ['type' => 'expression', 'uid' => $this->currentUser->id() ?: 0, 'created' => time()];
    foreach (['name', 'description', 'expression_type', 'documented_in'] as $k) {
      if (isset($data[$k])) {
        $values[$k] = $data[$k];
      }
    }
    $entity = $this->entityTypeManager->getStorage('assessment')->create($values);
    $entity->save();
    return (int) $entity->id();
  }

  /**
   * Creats a public service entity and returns its ID.
   */
  private function createPublicService(array $data): ?int {
    if ($data === []) {
      return NULL;
    }
    $values = ['type' => 'public_service', 'uid' => $this->currentUser->id() ?: 0, 'created' => time()];
    foreach (['name', 'thematic_area'] as $k) {
      if (isset($data[$k])) {
        $values[$k] = $data[$k];
      }
    }
    $entity = $this->entityTypeManager->getStorage('assessment')->create($values);
    $entity->save();
    return (int) $entity->id();
  }

  /**
   * Creats a public service entity and returns its ID.
   */
  private function createBindingRequirement(array $data): ?int {
    if ($data === []) {
      return NULL;
    }
    $values = ['type' => 'binding_requirement', 'uid' => $this->currentUser->id() ?: 0, 'created' => time()];
    foreach (['name', 'documented_in'] as $k) {
      if (isset($data[$k])) {
        $values[$k] = $data[$k];
      }
    }
    $entity = $this->entityTypeManager->getStorage('assessment')->create($values);
    $entity->save();
    return (int) $entity->id();
  }

  /**
   * Creates a participation entity and returns its ID.
   */
  private function createParticipation(array $data): ?int {
    if ($data === []) {
      return NULL;
    }
    $values = ['type' => 'participation', 'uid' => $this->currentUser->id() ?: 0, 'created' => time()];
    foreach (['country', 'participation_type'] as $k) {
      if (isset($data[$k])) {
        $values[$k] = $data[$k];
      }
    }
    $entity = $this->entityTypeManager->getStorage('assessment')->create($values);
    $entity->save();
    return (int) $entity->id();
  }

  /**
   * Creates a participation entity and returns its ID.
   */
  private function createAsset(array $data): ?int {
    if ($data === []) {
      return NULL;
    }
    $values = ['type' => 'asset', 'uid' => $this->currentUser->id() ?: 0, 'created' => time()];
    foreach (['name', 'landing_page'] as $k) {
      if (isset($data[$k])) {
        $values[$k] = $data[$k];
      }
    }
    $entity = $this->entityTypeManager->getStorage('assessment')->create($values);
    $entity->save();
    return (int) $entity->id();
  }

  /**
   * Creates a participation entity and returns its ID.
   */
  private function createStatement(array $data): ?int {
    if ($data === []) {
      return NULL;
    }
    $values = ['type' => 'statement', 'uid' => $this->currentUser->id() ?: 0, 'created' => time()];
    foreach (['judgment', 'interpretation', 'statement_type'] as $k) {
      if (isset($data[$k])) {
        $values[$k] = $data[$k];
      }
    }
    $entity = $this->entityTypeManager->getStorage('assessment')->create($values);
    $entity->save();
    return (int) $entity->id();
  }

  /**
   * GET: Returns a list of assessment entities with basic fields.
   */
  public function get(): ResourceResponse {
    $storage = $this->entityTypeManager->getStorage('assessment');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->range(0, 50)
      ->execute();

    $items = [];
    foreach ($storage->loadMultiple($ids) as $entity) {
      $items[] = [
        'id' => (int) $entity->id(),
        'uuid' => (string) $entity->uuid(),
        'bundle' => $entity->bundle(),
        'status' => (bool) $entity->get('status')->value,
        'owner' => (int) $entity->getOwnerId(),
        'created' => (int) $entity->getCreatedTime(),
        'changed' => (int) $entity->getChangedTime(),
        'links' => [
          'self' => $entity->toUrl('canonical', ['absolute' => TRUE])->toString(),
          'rest' => '/entity/assessment/' . $entity->id(),
        ],
      ];
    }

    $response = new ResourceResponse([
      'count' => count($items),
      'items' => $items,
    ], 200);
    $response->addCacheableDependency((object) ['#cache' => ['max-age' => 0]]);
    return $response;
  }

  /**
   * POST: Creates a new assessment entity.
   *
   * Expected JSON body example:
   * {
   *   "type": "public_organisation", // bundle machine name
   *   "status": true,
   *   "fields": {
   *     "provided_by": 123,
   *     "expression": [1, 2],
   *     ...
   *   }
   * }
   */
  public function post(Request $request): ResourceResponse {
    $content = $request->getContent();
    if ($content === '' || $content === NULL) {
      throw new BadRequestHttpException('Empty request body.');
    }

    $data = json_decode($content, TRUE);
    if (!is_array($data)) {
      throw new BadRequestHttpException('Invalid JSON payload.');
    }

    $values = [
      'type' => 'report',
    ];

    // Optional status.
    if (array_key_exists('status', $data)) {
      $values['status'] = (bool) $data['status'];
    }

    // Set owner to current user when available.
    $values['uid'] = $this->currentUser->id() ?: 0;

    // Build referenced sub-entities from embedded definitions.
    // Provided by (single Public Organization)
    if (!empty($data['provided_by']) && is_array($data['provided_by'])) {
      $org_id = $this->createPublicOrganization($data['provided_by']);
      if ($org_id) {
        $values['provided_by'] = ['target_id' => $org_id];
      }
    }
    // Expression (array)
    if (!empty($data['expression']) && is_array($data['expression'])) {
      $values['expression'] = [];
      foreach ($data['expression'] as $expr) {
        if (is_array($expr)) {
          $id = $this->createExpression($expr);
          if ($id) {
            $values['expression'][] = ['target_id' => $id];
          }
        }
      }
    }
    // Services.
    if (!empty($data['has_related_service']) && is_array($data['has_related_service'])) {
      $values['has_related_service'] = [];
      foreach ($data['has_related_service'] as $svc) {
        if (is_array($svc)) {
          $id = $this->createPublicService($svc);
          if ($id) {
            $values['has_related_service'][] = ['target_id' => $id];
          }
        }
      }
    }
    // Binding requirements.
    if (!empty($data['binding_requirement']) && is_array($data['binding_requirement'])) {
      $values['binding_requirement'] = [];
      foreach ($data['binding_requirement'] as $br) {
        if (is_array($br)) {
          $id = $this->createBindingRequirement($br);
          if ($id) {
            $values['binding_requirement'][] = ['target_id' => $id];
          }
        }
      }
    }
    // Participation.
    if (!empty($data['affected_party']) && is_array($data['affected_party'])) {
      $values['affected_party'] = [];
      foreach ($data['affected_party'] as $part) {
        if (is_array($part)) {
          $id = $this->createParticipation($part);
          if ($id) {
            $values['affected_party'][] = ['target_id' => $id];
          }
        }
      }
    }
    // Assets.
    if (!empty($data['asset']) && is_array($data['asset'])) {
      $values['asset'] = [];
      foreach ($data['asset'] as $asset) {
        if (is_array($asset)) {
          $id = $this->createAsset($asset);
          if ($id) {
            $values['asset'][] = ['target_id' => $id];
          }
        }
      }
    }
    // Statements.
    if (!empty($data['results_in']) && is_array($data['results_in'])) {
      $values['results_in'] = [];
      foreach ($data['results_in'] as $stmt) {
        if (is_array($stmt)) {
          $id = $this->createStatement($stmt);
          if ($id) {
            $values['results_in'][] = ['target_id' => $id];
          }
        }
      }
    }
    // Requirement documents (files by URI)
    if (!empty($data['requirement_documents']) && is_array($data['requirement_documents'])) {
      $values['requirement_documents'] = [];
      foreach ($data['requirement_documents'] as $file) {
        if (is_array($file) && !empty($file['uri'])) {
          $values['requirement_documents'][] = ['uri' => $file['uri']];
        }
      }
    }
    // Statement documents.
    if (!empty($data['statement_documents']) && is_array($data['statement_documents'])) {
      $values['statement_documents'] = [];
      foreach ($data['statement_documents'] as $file) {
        if (is_array($file) && !empty($file['uri'])) {
          $values['statement_documents'][] = ['uri' => $file['uri']];
        }
      }
    }
    // Name.
    if (!empty($data['name'])) {
      $values['name'] = $data['name'];
    }

    try {
      $entity = $this->entityTypeManager->getStorage('assessment')->create($values);
      $entity->save();
    }
    catch (\Throwable $e) {
      // Surface validation errors in a consistent way.
      $message = $e->getMessage();
      $this->logger->error('Failed to create assessment: @m', ['@m' => $message]);
      throw new HttpException(400, 'Could not create assessment: ' . $message);
    }

    $payload = [
      'id' => (int) $entity->id(),
      'uuid' => (string) $entity->uuid(),
      'bundle' => $entity->bundle(),
      'status' => (bool) $entity->get('status')->value,
    ];

    $response = new ResourceResponse($payload, 201);
    $response->headers->set('Location', $entity->toUrl('canonical', ['absolute' => TRUE])->toString());
    // No cache for creation response.
    $response->addCacheableDependency((object) ['#cache' => ['max-age' => 0]]);
    return $response;
  }

}
