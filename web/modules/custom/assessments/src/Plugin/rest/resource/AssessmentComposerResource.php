<?php

namespace Drupal\assessments\Plugin\rest\resource;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\rest\ResourceResponse;
use Drupal\rest\Annotation\RestResource;
use Drupal\rest\Plugin\ResourceBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Provides a REST resource to compose an assessment with nested entities.
 *
 * @RestResource(
 *   id = "assessments.composer",
 *   label = @Translation("Assessment Composer"),
 *   serialization_class = "",
 *   uri_paths = {
 *     "canonical" = "/assessments/compose",
 *     "create" = "/assessments/compose"
 *   }
 * )
 */
#[\Drupal\rest\Attribute\RestResource(
  id: "assessments.composer",
  label: new TranslatableMarkup("Assessment Composer"),
  uri_paths: [
    "create" => "/assessments/compose",
  ]
)]
class AssessmentComposerResource extends ResourceBase {

  /**
   * {@inheritdoc}
   */
  public function permissions(): array {
    // Reuse a broad permission. Adjust as needed for stricter control.
    return [
      'POST' => ['access content'],
    ];
  }

  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected EntityTypeManagerInterface $entityTypeManager, protected Connection $db) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('database')
    );
  }

  /**
   * Handles POST requests to compose an assessment.
   */
  public function post(Request $request): ResourceResponse {
    $data = json_decode($request->getContent(), TRUE);
    if (!is_array($data)) {
      throw new BadRequestHttpException('Invalid JSON.');
    }

    $transaction = $this->db->startTransaction();
    try {
      // Create or load Public Organization.
      $organization_id = NULL;
      if (!empty($data['organization'])) {
        $organization_payload = $data['organization'];
        $organization = $this->createEntityFromPayload('assessment_organization', $organization_payload, ['label']);
        $organization_id = $organization->id();
      }
      else {
        throw new UnprocessableEntityHttpException('organization section is required');
      }

      // Create or load Binding Requirement.
      $requirement_id = NULL;
      if (!empty($data['requirement'])) {
        $requirement_payload = $data['requirement'];
        $requirement = $this->createEntityFromPayload('assessment_requirement', $requirement_payload, ['label']);
        $requirement_id = $requirement->id();
      }
      else {
        throw new UnprocessableEntityHttpException('requirement section is required');
      }

      // Create Statements, supporting nested services under each statement (no participant links).
      $statement_ids = [];
      $statements_response = [];
      if (!empty($data['statements']) && is_array($data['statements'])) {
        foreach ($data['statements'] as $statement_payload) {
          $nested_services = $statement_payload['services'] ?? [];
          unset($statement_payload['participants'], $statement_payload['services']);

          $statement = $this->createEntityFromPayload('assessment_statement', $statement_payload, ['name']);
          $statement_ids[] = $statement->id();

          // Build response children for services only (no participants on statements).
          $statement_children = [];
          if ($nested_services) {
            $statement_children['services'] = [];
            $attached_service_ids = [];
            foreach ($nested_services as $s_payload) {
              $service = $this->createEntityFromPayload('assessment_service', $s_payload);
              $attached_service_ids[] = $service->id();
              $statement_children['services'][] = $service->toArray();
            }
          }
          // Attach created service references to the statement entity if available.
          if (!empty($attached_service_ids)) {
            $statement->set('services', $attached_service_ids);
            $statement->save();
          }
          $statements_response[] = $statement->toArray() + $statement_children;
        }
      }

      // Optional nested: allow requirement.participant, requirement.affected_party and participant.agent nesting.
      $created_children = [];
      // requirement nested handling
      if (!empty($data['requirement']) && is_array($data['requirement'])) {
        // Already created $requirement above; here, process nested children for response consistency.
        $req_children = [];
        $req_participants = $data['requirement']['participant'] ?? $data['requirement']['participants'] ?? [];
        if ($req_participants) {
          $req_children['participants'] = [];
          foreach ((array) $req_participants as $p_payload) {
            $agent_payloads = $p_payload['agent'] ?? $p_payload['agents'] ?? [];
            unset($p_payload['agent'], $p_payload['agents']);
            $created_agents = [];
            if ($agent_payloads) {
              $first = null;
              foreach ((array) $agent_payloads as $a_payload) {
                $agent = $this->createEntityFromPayload('assessments_agent', $a_payload);
                $created_agents[] = $agent->toArray();
                if ($first === null) {
                  $first = $agent;
                }
              }
              $p_payload['agent'] = $first?->id();
            }
            $participant = $this->createEntityFromPayload('assessments_participation', $p_payload);
            $p_array = $participant->toArray();
            if ($created_agents) {
              $p_array['agents'] = $created_agents;
            }
            $req_children['participants'][] = $p_array;
          }
        }
        $req_service = $data['requirement']['affected_party'] ?? $data['requirement']['service'] ?? null;
        if ($req_service) {
          $service = $this->createEntityFromPayload('assessment_service', (array) $req_service);
          $req_children['affected_party'] = $service->toArray();
        }
        if ($req_children) {
          $created_children['requirement_children'] = $req_children;
        }
      }

      // Also support top-level arrays as before for backward compatibility.
      foreach ([
        'participants' => 'assessments_participation',
        'agents' => 'assessments_agent',
        'expressions' => 'assessment_expression',
      ] as $key => $entity_type) {
        if (!empty($data[$key]) && is_array($data[$key])) {
          $created_children[$key] = [];
          foreach ($data[$key] as $payload) {
            $entity = $this->createEntityFromPayload($entity_type, $payload);
            $created_children[$key][] = $entity->toArray();
          }
        }
      }

      // Create Assessment root and set references.
      $assessment_values = [
        'status' => isset($data['status']) ? (bool) $data['status'] : TRUE,
        'organization' => $organization_id,
        'requirement' => $requirement_id,
      ];
      if ($statement_ids) {
        $assessment_values['statements'] = $statement_ids;
      }
      $assessment = $this->entityTypeManager->getStorage('assessment')->create($assessment_values);
      $assessment->save();

      $response = [
        'assessment' => $assessment->toArray(),
        'organization' => $this->entityToArray('assessment_organization', $organization_id),
      ];
      // Merge requirement base with any created nested children under requirement.
      $requirement_array = $this->entityToArray('assessment_requirement', $requirement_id);
      if (!empty($created_children['requirement_children'])) {
        $requirement_array += $created_children['requirement_children'];
        unset($created_children['requirement_children']);
      }
      $response['requirement'] = $requirement_array;

      if (!empty($statements_response)) {
        $response['statements'] = $statements_response;
      } elseif ($statement_ids) {
        $response['statements'] = array_map(fn($id) => $this->entityToArray('assessment_statement', $id), $statement_ids);
      }
      $response += $created_children;

      return (new ResourceResponse($response, 201))->addCacheableDependency($assessment);
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }
  }

  /**
   * Create an entity from a simple payload.
   * Supports direct field assignment of primitive fields.
   */
  protected function createEntityFromPayload(string $entity_type, array $payload, array $required_fields = []) {
    if (!is_array($payload)) {
      throw new UnprocessableEntityHttpException("Invalid payload for $entity_type");
    }
    foreach ($required_fields as $field) {
      if (!array_key_exists($field, $payload)) {
        throw new UnprocessableEntityHttpException("Missing required field '$field' for $entity_type");
      }
    }
    // Allow client to provide uuid to upsert? Minimal: always create new when no uuid.
    $storage = $this->entityTypeManager->getStorage($entity_type);
    // For composer endpoint, we always create new entities without reusing existing ones.
    // If a UUID is provided in the payload, force it as the entity UUID so the
    // client can rely on it as identifier of the newly created entity.
    $entity = $storage->create($payload);
    $entity->save();
    return $entity;
  }

  protected function entityToArray(string $entity_type, $id): array {
    $storage = $this->entityTypeManager->getStorage($entity_type);
    $entity = $storage->load($id);
    return $entity ? $entity->toArray() : [];
  }
}
