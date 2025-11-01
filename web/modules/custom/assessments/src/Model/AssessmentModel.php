<?php

declare(strict_types=1);

namespace Drupal\assessments\Model;

/**
 * Defines the assessment model class.
 */
class AssessmentModel extends AssessmentModelBase implements AssessmentModelInterface {

  /**
   * Child models collected from the payload.
   *
   * @var \Drupal\assessments\Model\BindingRequirementModel[]
   */
  protected array $bindingRequirements = [];

  /**
   * @var \Drupal\assessments\Model\StatementModel[]
   */
  protected array $statements = [];

  /** @var \Drupal\assessments\Model\ExpressionModel[] */
  protected array $expressions = [];
  /** @var \Drupal\assessments\Model\PublicServiceModel[] */
  protected array $publicServices = [];
  /** @var \Drupal\assessments\Model\ParticipationModel[] */
  protected array $participations = [];
  /** @var \Drupal\assessments\Model\AssetModel[] */
  protected array $assets = [];
  /** @var \Drupal\assessments\Model\FileModel[] */
  protected array $requirementDocuments = [];
  /** @var \Drupal\assessments\Model\FileModel[] */
  protected array $statementDocuments = [];
  protected ?OrganisationModel $provider = NULL;

  /**
   * Processes and normalizes the incoming data into child models.
   */
  public function process(): void {
    $asList = static function ($value) {
      if ($value === NULL) { return []; }
      return is_array($value) && array_is_list($value) ? $value : [$value];
    };

    // binding_requirement can be array of UUIDs or structured objects.
    foreach ($asList($this->data['binding_requirement'] ?? []) as $br) {
      $brData = is_array($br) ? $br : ['_uuid' => (string) $br];
      $bindingRequirement = new BindingRequirementModel([
        'name' => $brData['name'] ?? NULL,
        'documented_in' => $brData['documented_in'] ?? NULL,
        '_uuid' => $brData['_uuid'] ?? NULL,
      ]);
      $bindingRequirement->process();
      $this->bindingRequirements[] = $bindingRequirement;

      if (!is_array($br)) {
        // UUID-only item, nothing nested to lift.
        continue;
      }

      // Lift nested items per README compromises.
      foreach ($asList($br['expression'] ?? []) as $expr) {
        $exprData = is_array($expr) ? $expr : ['_uuid' => (string) $expr];
        $model = new ExpressionModel([
          'name' => $exprData['name'] ?? NULL,
          'description' => $exprData['description'] ?? NULL,
          // The openapi uses document_in; Joinup uses documented_in.
          'documented_in' => $exprData['documented_in'] ?? ($exprData['document_in'] ?? NULL),
          'expression_type' => $exprData['expression_type'] ?? NULL,
          '_uuid' => $exprData['_uuid'] ?? NULL,
        ]);
        $this->expressions[] = $model;
      }

      foreach ($asList($br['affects'] ?? []) as $ps) {
        $psData = is_array($ps) ? $ps : ['_uuid' => (string) $ps];
        $model = new PublicServiceModel([
          'name' => $psData['name'] ?? NULL,
          'thematic_area' => $psData['thematic_area'] ?? NULL,
          '_uuid' => $psData['_uuid'] ?? NULL,
        ]);
        $this->publicServices[] = $model;
      }

      foreach ($asList($br['has_participation'] ?? []) as $part) {
        $pData = is_array($part) ? $part : ['_uuid' => (string) $part];
        $model = new ParticipationModel([
          'country' => $pData['country'] ?? NULL,
          'participation_type' => $pData['participation_type'] ?? NULL,
          '_uuid' => $pData['_uuid'] ?? NULL,
        ]);
        $this->participations[] = $model;
      }

      foreach ($asList($br['asset'] ?? []) as $asset) {
        $aData = is_array($asset) ? $asset : ['_uuid' => (string) $asset];
        $model = new AssetModel([
          'name' => $aData['name'] ?? NULL,
          'landing_page' => $aData['landing_page'] ?? NULL,
          '_uuid' => $aData['_uuid'] ?? NULL,
        ]);
        $this->assets[] = $model;
      }

      foreach ($asList($br['documents'] ?? []) as $doc) {
        $dData = is_array($doc) ? $doc : ['_uuid' => (string) $doc];
        $model = new FileModel([
          'file_uri' => $dData['file_uri'] ?? NULL,
          '_uuid' => $dData['_uuid'] ?? NULL,
        ]);
        $this->requirementDocuments[] = $model;
      }
    }

    // provider
    if (!empty($this->data['provider'])) {
      $prov = $this->data['provider'];
      $provData = is_array($prov) ? $prov : ['_uuid' => (string) $prov];
      $this->provider = new OrganisationModel([
        'name' => $provData['name'] ?? NULL,
        'organisation_type' => $provData['organisation_type'] ?? ($provData['organization_type'] ?? NULL),
        'country' => $provData['country'] ?? NULL,
        '_uuid' => $provData['_uuid'] ?? NULL,
      ]);
    }

    // results_in (statements) with their documents.
    foreach ($asList($this->data['results_in'] ?? []) as $st) {
      $stData = is_array($st) ? $st : ['_uuid' => (string) $st];
      $statement = new StatementModel([
        'interpretation' => $stData['interpretation'] ?? NULL,
        'judgement' => $stData['judgement'] ?? NULL,
        'statement_type' => $stData['statement_type'] ?? ($stData['type'] ?? NULL),
        '_uuid' => $stData['_uuid'] ?? NULL,
      ]);
      $this->statements[] = $statement;

      if (is_array($st)) {
        foreach ($asList($st['documents'] ?? []) as $doc) {
          $dData = is_array($doc) ? $doc : ['_uuid' => (string) $doc];
          $model = new FileModel([
            'file_uri' => $dData['file_uri'] ?? NULL,
            '_uuid' => $dData['_uuid'] ?? NULL,
          ]);
          $this->statementDocuments[] = $model;
        }
      }
    }
  }

  protected function bundle(): string { return 'assessment'; }

  protected function getEntityValues(): array {
    // For validation, we only return minimal values; save() will build refs.
    return [
      'name' => $this->data['name'] ?? NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validate(): array {
    $errors = parent::validate();
    foreach ([
      $this->bindingRequirements,
      $this->statements,
      $this->expressions,
      $this->publicServices,
      $this->participations,
      $this->assets,
      $this->requirementDocuments,
      $this->statementDocuments,
    ] as $list) {
      foreach ($list as $model) {
        $errors = array_merge($errors, $model->validate());
      }
    }
    if ($this->provider) {
      $errors = array_merge($errors, $this->provider->validate());
    }
    return $errors;
  }

  /**
   * {@inheritdoc}
   */
  public function save(): void {
    // Save children first and collect their IDs.
    $saveAll = static function (array $models): array {
      $ids = [];
      foreach ($models as $m) {
        $m->save();
        if ($m->getSavedId()) {
          $ids[] = $m->getSavedId();
        }
      }
      return $ids;
    };

    $bindingReqIds = $saveAll($this->bindingRequirements);
    $statementIds = $saveAll($this->statements);
    $expressionIds = $saveAll($this->expressions);
    $serviceIds = $saveAll($this->publicServices);
    $participationIds = $saveAll($this->participations);
    $assetIds = $saveAll($this->assets);
    $reqDocIds = $saveAll($this->requirementDocuments);
    $stmtDocIds = $saveAll($this->statementDocuments);

    $providerId = NULL;
    if ($this->provider) {
      $this->provider->save();
      $providerId = $this->provider->getSavedId();
    }

    $values = [
      'name' => $this->data['name'] ?? NULL,
      'state' => 'under_review',
      'binding_requirement' => $bindingReqIds,
      'affected_party' => $participationIds,
      'has_related_service' => $serviceIds,
      'asset' => $assetIds,
      'expression' => $expressionIds,
      'provided_by' => $providerId ? [$providerId] : [],
      'results_in' => $statementIds,
      'requirement_documents' => $reqDocIds,
      'statement_documents' => $stmtDocIds,
    ];

    $storage = \Drupal::entityTypeManager()->getStorage('assessment_content');
    $entity = $storage->create(['type' => $this->bundle(), 'created' => REQUEST_TIME, 'uid' => \Drupal::currentUser()->id()] + $values);
    $entity->save();
    $this->createdId = (string) $entity->id();
  }

}
