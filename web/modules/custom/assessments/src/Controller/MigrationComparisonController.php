<?php

declare(strict_types=1);

namespace Drupal\assessments\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for comparing migrated webform submissions with reports.
 */
class MigrationComparisonController extends ControllerBase {

  public function __construct(
    protected readonly Connection $database,
    protected readonly DateFormatterInterface $dateFormatter,
    EntityTypeManagerInterface $entityTypeManager,
  ) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('date.formatter'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Lists all migrated submissions and their assessment reports.
   */
  public function list(): array {
    $header = [
      ['data' => $this->t('Webform SID'), 'field' => 'm.sid', 'sort' => 'asc'],
      ['data' => $this->t('Report name'), 'field' => 'a.name'],
      ['data' => $this->t('Migration date'), 'field' => 'm.migrated'],
      $this->t('Has barriers'),
      $this->t('Has assessment docs'),
      $this->t('Has requirement docs'),
      $this->t('Has services'),
      $this->t('Has participation'),
      $this->t('Has assets'),
      $this->t('Operations'),
    ];

    $query = $this->database->select('assessments_migration_map', 'm')
      ->extend('\Drupal\Core\Database\Query\TableSortExtender')
      ->extend('\Drupal\Core\Database\Query\PagerSelectExtender');

    $query->join('assessment_field_data', 'a', 'm.report_id = a.id');
    $query->fields('m', ['sid', 'report_id', 'migrated']);
    $query->fields('a', ['name']);
    $query->orderByHeader($header);
    $query->limit(50);

    $results = $query->execute()->fetchAll();

    // Bulk-load webform submissions for the current page.
    $sids = array_column($results, 'sid');
    $submissions = [];
    if ($sids) {
      $submissionStorage = $this->entityTypeManager()->getStorage('webform_submission');
      $submissions = $submissionStorage->loadMultiple($sids);
    }

    $yes = $this->t('Yes');
    $no = $this->t('No');
    $rows = [];

    foreach ($results as $row) {
      $data = isset($submissions[$row->sid]) ? $submissions[$row->sid]->getData() : [];

      $hasBarriers = !empty($data['binding_requirement_assessed']) || !empty($data['binding_requirements_identified_concern']);
      $hasAssessmentDocs = !empty($data['upload_assessment_documents']);
      $hasRequirementDocs = !empty($data['binding_requirement_documents']);
      $hasServices = !empty(array_filter((array) ($data['trans_european_digital_public_service_concerned'] ?? [])));
      $hasParticipation = !empty(array_filter((array) ($data['data_processed_by'] ?? [])));
      $hasAssets = !empty(array_filter((array) ($data['portal_solutions'] ?? [])))
        || !empty(array_filter(array_map(
          fn($link) => !empty($link['title']) || !empty($link['url']),
          (array) ($data['portal_solutions_link'] ?? []),
        )));

      $rows[] = [
        $row->sid,
        [
          'data' => [
            '#type' => 'link',
            '#title' => $row->sid,
            '#url' => Url::fromRoute('entity.webform_submission.canonical', [
              'webform' => 'assessment',
              'webform_submission' => $row->sid,
            ]),
          ],
        ],
        [
          'data' => [
            '#type' => 'link',
            '#title' => $row->name,
            '#url' => Url::fromRoute('entity.assessment.canonical', [
              'assessment_type' => 'report',
              'assessment' => $row->report_id,
            ]),
          ],
        ],
        $this->dateFormatter->format((int) $row->migrated, 'short'),
        $hasBarriers ? $yes : $no,
        $hasAssessmentDocs ? $yes : $no,
        $hasRequirementDocs ? $yes : $no,
        $hasServices ? $yes : $no,
        $hasParticipation ? $yes : $no,
        $hasAssets ? $yes : $no,
        [
          'data' => [
            '#type' => 'link',
            '#title' => $this->t('Compare'),
            '#url' => Url::fromRoute('assessments.migration_compare', ['report_id' => $row->report_id]),
          ],
        ],
      ];
    }

    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No migrated submissions found.'),
    ];

    $build['pager'] = [
      '#type' => 'pager',
    ];

    return $build;
  }

  /**
   * Compares a webform submission with its migrated assessment report.
   */
  public function compare(int $report_id): array {
    $mapping = $this->database->select('assessments_migration_map', 'm')
      ->fields('m', ['sid', 'report_id', 'migrated'])
      ->condition('m.report_id', $report_id)
      ->execute()
      ->fetchObject();

    if (!$mapping) {
      throw new NotFoundHttpException();
    }

    $report = $this->entityTypeManager()->getStorage('assessment')->load($mapping->report_id);
    if (!$report) {
      throw new NotFoundHttpException();
    }

    $submission = $this->entityTypeManager()->getStorage('webform_submission')->load($mapping->sid);
    $webformData = $submission ? $submission->getData() : [];

    // Load the provider entity and extract its fields.
    $provider = NULL;
    $providerEntities = $report->get('provider')->referencedEntities();
    if (!empty($providerEntities)) {
      $provider = reset($providerEntities);
    }

    $rows = [];

    // Report fields.
    $rows[] = [
      ['data' => $this->t('Report fields'), 'header' => TRUE, 'colspan' => 3],
    ];

    // Name / short_title.
    $rows[] = [
      $this->t('Name'),
      $webformData['short_title'] ?? '',
      $report->get('name')->value ?? '',
    ];

    // Author (uid).
    $webformAuthor = '';
    if ($submission) {
      $owner = $submission->getOwner();
      $webformAuthor = $owner
        ? $owner->getDisplayName() . ' (' . $owner->id() . ')'
        : (string) $submission->getOwnerId();
    }
    $reportOwner = $report->getOwner();
    $reportAuthor = $reportOwner
      ? $reportOwner->getDisplayName() . ' (' . $reportOwner->id() . ')'
      : '';
    $rows[] = [
      $this->t('Author'),
      $webformAuthor,
      $reportAuthor,
    ];

    // State.
    $stateWebform = $submission ? ($submission->isDraft() ? 'draft' : 'review') : '';
    $rows[] = [
      $this->t('State'),
      $stateWebform,
      $report->get('state')->value ?? '',
    ];

    // Provider (organisation) fields.
    $rows[] = [
      ['data' => $this->t('Provider (Public Organisation)'), 'header' => TRUE, 'colspan' => 3],
    ];

    // Organisation name.
    $rows[] = [
      $this->t('Organisation name'),
      $webformData['organisation'] ?? '',
      $provider ? ($provider->get('name')->value ?? '') : '',
    ];

    // Organisation type.
    $providerTypeWebform = $webformData['public_sector_body'] ?? '';
    $providerTypeReport = '';
    if ($provider) {
      $orgTypeEntities = $provider->get('organisation_type')->referencedEntities();
      if (!empty($orgTypeEntities)) {
        $providerTypeReport = reset($orgTypeEntities)->label();
      }
    }
    $rows[] = [
      $this->t('Organisation type'),
      $providerTypeWebform,
      $providerTypeReport,
    ];

    // Organisation country.
    $countryWebform = $webformData['country'] ?? '';
    $countryReport = '';
    if ($provider) {
      $countryEntities = $provider->get('country')->referencedEntities();
      if (!empty($countryEntities)) {
        $countryReport = reset($countryEntities)->label();
      }
    }
    $rows[] = [
      $this->t('Country'),
      $countryWebform,
      $countryReport,
    ];

    // Statements (interoperability barriers).
    $statementTypes = [
      'legal' => $this->t('Legal'),
      'organisational' => $this->t('Organisational'),
      'semantic' => $this->t('Semantic'),
      'technical' => $this->t('Technical'),
    ];
    $rows[] = [
      ['data' => $this->t('Statements'), 'header' => TRUE, 'colspan' => 3],
    ];

    foreach ($statementTypes as $type => $label) {
      // Extract webform values.
      $webformStatementData = $webformData["identified_effects_cross_border_{$type}"] ?? NULL;
      $webformInterpretation = '';
      $webformJudgement = '';
      if (!empty($webformStatementData)) {
        if (isset($webformStatementData[0]) && is_array($webformStatementData[0])) {
          $webformStatementData = $webformStatementData[0];
        }
        $webformInterpretation = $webformStatementData['legal_options'] ?? '';
        $webformJudgement = $webformStatementData['organisational_options'] ?? '';
      }

      // Extract report values from the statement entity.
      $reportInterpretation = '';
      $reportJudgement = '';
      $statementField = $report->get($type);
      if (!$statementField->isEmpty()) {
        $statementEntities = $statementField->referencedEntities();
        if (!empty($statementEntities)) {
          $statement = reset($statementEntities);
          $interpEntities = $statement->get('interpretation')->referencedEntities();
          if (!empty($interpEntities)) {
            $reportInterpretation = reset($interpEntities)->label();
          }
          $reportJudgement = $statement->get('judgement')->value ?? '';
        }
      }

      $rows[] = [
        $this->t('@type: Interpretation', ['@type' => $label]),
        $webformInterpretation,
        $reportInterpretation,
      ];
      $rows[] = [
        $this->t('@type: Judgement', ['@type' => $label]),
        $webformJudgement,
        $reportJudgement,
      ];
    }

    // Remaining report fields.
    $rows[] = [
      ['data' => $this->t('Additional report fields'), 'header' => TRUE, 'colspan' => 3],
    ];

    // Binding requirements concern.
    $rows[] = [
      $this->t('Binding requirements concern'),
      $webformData['binding_requirements_identified_concern'] ?? '',
      '',
    ];

    // Remaining barriers.
    $barriersWebform = '';
    if (($webformData['remaining_barriers'] ?? '') === 'Yes') {
      $barriersWebform = $webformData['remaining_barriers_description'] ?? '';
    }
    $rows[] = [
      $this->t('Remaining barriers'),
      $barriersWebform,
      $report->get('remaining_barriers')->value ?? '',
    ];

    // Other comment.
    $commentWebform = '';
    if (!empty($webformData['comments'])) {
      $commentWebform = is_array($webformData['comments'])
        ? ($webformData['comments']['value'] ?? '')
        : $webformData['comments'];
    }
    $rows[] = [
      $this->t('Other comment'),
      $commentWebform,
      $report->get('other_comment')->value ?? '',
    ];

    // Relevant reports.
    $relevantWebform = '';
    if (($webformData['previous_assessments'] ?? '') === 'Yes') {
      $links = (array) ($webformData['reference_of_the_linked_assessment'] ?? []);
      $relevantWebform = implode(', ', array_filter($links));
    }
    $relevantReport = '';
    $relevantField = $report->get('relevant_report');
    $relevantValues = [];
    foreach ($relevantField as $item) {
      if (!empty($item->uri)) {
        $relevantValues[] = $item->uri;
      }
    }
    $relevantReport = implode(', ', $relevantValues);
    $rows[] = [
      $this->t('Relevant reports'),
      $relevantWebform,
      $relevantReport,
    ];

    // Assessment supporting documents.
    $fileStorage = $this->entityTypeManager()->getStorage('file');
    $webformDocNames = [];
    foreach ((array) ($webformData['upload_assessment_documents'] ?? []) as $fid) {
      if (!empty($fid)) {
        $file = $fileStorage->load($fid);
        if ($file) {
          $webformDocNames[] = $file->getFilename();
        }
      }
    }
    $reportDocNames = [];
    foreach ($report->get('documents')->referencedEntities() as $file) {
      $reportDocNames[] = $file->getFilename();
    }
    $rows[] = [
      $this->t('Supporting documents'),
      implode(', ', $webformDocNames),
      implode(', ', $reportDocNames),
    ];

    $build['comparison'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Field'),
        $this->t('Webform value'),
        $this->t('Report value'),
      ],
      '#rows' => $rows,
    ];

    // Binding requirements section.
    $bindingRequirements = $report->get('binding_requirement')->referencedEntities();
    $webformRequirements = (array) ($webformData['binding_requirement_assessed'] ?? []);

    // Webform shared data (same across all binding requirements).
    $webformInitiatives = [];
    foreach ((array) ($webformData['initiatives'] ?? []) as $initiative) {
      if (!empty($initiative['initiative'])) {
        $items = [];
        $items[] = $initiative['initiative'];
        if (!empty($initiative['initiative_link'])) {
          $items[] = $initiative['initiative_link'];
        }
        $webformInitiatives[] = $items;
      }
    }

    $webformServices = array_values(array_filter((array) ($webformData['trans_european_digital_public_service_concerned'] ?? [])));

    $webformParticipants = array_values(array_filter((array) ($webformData['data_processed_by'] ?? [])));

    $webformAssets = [];
    $nodeStorage = $this->entityTypeManager()->getStorage('node');
    foreach ((array) ($webformData['portal_solutions'] ?? []) as $nid) {
      if (!empty($nid)) {
        $solution = $nodeStorage->load($nid);
        if ($solution) {
          $webformAssets[] = $solution->label();
        }
      }
    }
    foreach ((array) ($webformData['portal_solutions_link'] ?? []) as $linkData) {
      if (!empty($linkData['title']) || !empty($linkData['url'])) {
        $webformAssets[] = !empty($linkData['title']) ? $linkData['title'] : $linkData['url'];
      }
    }

    $webformDocFiles = [];
    $fileStorage = $this->entityTypeManager()->getStorage('file');
    foreach ((array) ($webformData['binding_requirement_documents'] ?? []) as $fid) {
      if (!empty($fid)) {
        $file = $fileStorage->load($fid);
        if ($file) {
          $webformDocFiles[] = $file->getFilename();
        }
      }
    }

    $build['binding_requirements_heading'] = [
      '#markup' => '<h3>' . $this->t('Binding Requirements') . '</h3>',
    ];

    if (empty($bindingRequirements)) {
      $build['binding_requirements_empty'] = [
        '#markup' => '<p>' . $this->t('No binding requirements found on the report.') . '</p>',
      ];
    }

    foreach ($bindingRequirements as $index => $paragraph) {
      // Build webform cell content.
      $webformDescription = $webformRequirements[$index]['description_1'] ?? '';
      if (empty($webformDescription) && empty($webformRequirements)) {
        $webformDescription = (string) $this->t('(no binding requirements in webform)');
      }
      $webformLegalRef = $webformRequirements[$index]['legal_reference'] ?? '';

      $webformCell = $this->buildDefinitionList([
        (string) $this->t('Description') => $webformDescription,
        (string) $this->t('Legal reference') => $webformLegalRef,
        (string) $this->t('Initiatives') => $this->formatInitiativesList($webformInitiatives),
        (string) $this->t('Affected services') => implode(', ', $webformServices),
        (string) $this->t('Affected parties') => implode(', ', $webformParticipants),
        (string) $this->t('Assets') => implode(', ', $webformAssets),
        (string) $this->t('Documents') => implode(', ', $webformDocFiles),
      ]);

      // Build report cell content.
      $reportDescription = $paragraph->get('description')->value ?? '';

      $reportLegalRef = '';
      $docInField = $paragraph->get('documented_in');
      if (!$docInField->isEmpty()) {
        $reportLegalRef = $docInField->first()->uri ?? '';
      }

      $reportExpressions = [];
      foreach ($paragraph->get('expression')->referencedEntities() as $expression) {
        $items = [];
        $items[] = $expression->get('initiative_name')->value ?? $expression->label();
        $docIn = $expression->get('documented_in');
        if (!$docIn->isEmpty()) {
          $items[] = $docIn->first()->uri ?? '';
        }
        $reportExpressions[] = $items;
      }

      $reportServices = [];
      foreach ($paragraph->get('affects')->referencedEntities() as $service) {
        $reportServices[] = $service->label();
      }

      $reportParticipants = [];
      foreach ($paragraph->get('has_participation')->referencedEntities() as $participation) {
        foreach ($participation->get('participant_type')->referencedEntities() as $typeEntity) {
          $reportParticipants[] = $typeEntity->label();
        }
      }

      $reportAssets = [];
      foreach ($paragraph->get('asset')->referencedEntities() as $asset) {
        $reportAssets[] = $asset->label();
      }

      $reportDocs = [];
      foreach ($paragraph->get('requirement_documents')->referencedEntities() as $doc) {
        $reportDocs[] = $doc->getFilename();
      }

      $reportCell = $this->buildDefinitionList([
        (string) $this->t('Description') => $reportDescription,
        (string) $this->t('Legal reference') => $reportLegalRef,
        (string) $this->t('Initiatives') => $this->formatInitiativesList($reportExpressions),
        (string) $this->t('Affected services') => implode(', ', $reportServices),
        (string) $this->t('Affected parties') => implode(', ', $reportParticipants),
        (string) $this->t('Assets') => implode(', ', $reportAssets),
        (string) $this->t('Documents') => implode(', ', $reportDocs),
      ]);

      $build['binding_requirement_' . $index] = [
        '#type' => 'details',
        '#title' => $this->t('Binding Requirement #@num', ['@num' => $index + 1]),
        '#open' => TRUE,
        'table' => [
          '#type' => 'table',
          '#header' => [
            $this->t('Webform'),
            $this->t('Report'),
          ],
          '#rows' => [
            [
              ['data' => $webformCell, 'style' => 'vertical-align: top; width: 50%;'],
              ['data' => $reportCell, 'style' => 'vertical-align: top; width: 50%;'],
            ],
          ],
        ],
      ];
    }

    $build['back'] = [
      '#type' => 'link',
      '#title' => $this->t('Back to list'),
      '#url' => Url::fromRoute('assessments.migration_list'),
      '#attributes' => ['class' => ['button']],
    ];

    return $build;
  }

  /**
   * Builds a render array for a definition list.
   *
   * @param array<string, string> $items
   *   An associative array of label => value pairs.
   *
   * @return array
   *   A render array.
   */
  protected function buildDefinitionList(array $items): array {
    $elements = [];
    foreach ($items as $label => $value) {
      $elements[] = [
        '#markup' => '<dt><strong>' . $label . '</strong></dt><dd>' . ($value !== '' ? $value : '<em>' . $this->t('(empty)') . '</em>') . '</dd>',
      ];
    }
    return [
      '#prefix' => '<dl>',
      '#suffix' => '</dl>',
      'items' => $elements,
    ];
  }

  /**
   * Formats a list of initiatives for display.
   *
   * Each initiative is an array where the first element is the name and the
   * optional second element is the link.
   *
   * @param list<list<string>> $initiatives
   *   List of initiatives, each being [name] or [name, link].
   *
   * @return string
   *   Formatted string.
   */
  protected function formatInitiativesList(array $initiatives): string {
    if (empty($initiatives)) {
      return '';
    }
    $parts = [];
    foreach ($initiatives as $initiative) {
      $entry = $initiative[0] ?? '';
      if (!empty($initiative[1])) {
        $entry .= ' (' . $initiative[1] . ')';
      }
      $parts[] = $entry;
    }
    return implode('; ', $parts);
  }

}
