<?php

declare(strict_types=1);

namespace Drupal\assessments;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\assessments\Entity\PublicOrganisation;
use Drupal\webform\WebformSubmissionInterface;
use Psr\Log\LoggerInterface;

/**
 * Migrates webform assessment submissions into assessment report entities.
 */
class WebformSubmissionMigrator {

  use ProvidedByHelperTrait;

  /**
   * Map of webform provider type labels to taxonomy identifiers.
   */
  protected const array PROVIDER_TYPE_MAP = [
    'Local public sector body' => 'local_public_sector_body',
    'Regional public sector body' => 'regional_public_sector_body',
    'National public sector body' => 'national_public_sector_body',
    'European Union institution' => 'european_union_institution',
    'European Union agency' => 'european_union_agency',
    'European Union body' => 'european_union_body',
  ];

  /**
   * Map of webform thematic area labels to taxonomy identifiers.
   */
  protected const array THEMATIC_AREA_MAP = [
    'Defense' => 'defence',
    'Defence' => 'defence',
    'Economic affairs' => 'economic_affairs',
    'Education' => 'education',
    'Environmental Protection' => 'environmental_protection',
    'Environmental protection' => 'environmental_protection',
    'General public services' => 'general_public_services',
    'Health' => 'health',
    'Housing and Community Amenities' => 'housing_and_community_amenities',
    'Housing and community amenities' => 'housing_and_community_amenities',
    'Public Order and Safety' => 'public_order_and_safety',
    'Public order and safety' => 'public_order_and_safety',
    'Recreation, Culture, and Religion' => 'recreation_culture_and_religion',
    'Recreation, culture and religion' => 'recreation_culture_and_religion',
    'Social Protection' => 'social_protection',
    'Social protection' => 'social_protection',
  ];

  /**
   * Map of webform interpretation labels to taxonomy identifiers.
   */
  protected const array INTERPRETATION_MAP = [
    'Beneficial' => 'positive',
    'Negligible' => 'neutral',
    'Risky' => 'negative',
  ];

  /**
   * Map of webform participant type labels to taxonomy identifiers.
   *
   * Includes trailing-space variant found in webform config.
   */
  protected const array PARTICIPANT_TYPE_MAP = [
    'Local public sector body' => 'local_public_sector_body',
    'Regional public sector body' => 'regional_public_sector_body',
    'National public sector body' => 'national_public_sector_body',
    'European Union institution' => 'european_union_institution',
    'European Union agency' => 'european_union_agency',
    'European Union body' => 'european_union_body',
    'Private Businesses' => 'private_businesses',
    'Private Businesses ' => 'private_businesses',
  ];

  /**
   * Map of webform country names to spatial taxonomy term names.
   *
   * Used to normalize country names from webform data to match taxonomy terms.
   */
  protected const array COUNTRY_NAME_MAP = [
    'EU' => 'European Union',
    'Czech Republic' => 'Czechia',
  ];

  /**
   * Assessment entity storage.
   */
  protected ContentEntityStorageInterface $assessmentStorage;

  /**
   * The logger.
   */
  protected readonly LoggerInterface $logger;

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly AssessmentVocabularyHelper $vocabularyHelper,
    protected readonly EntityRepositoryInterface $entityRepository,
    LoggerChannelFactoryInterface $loggerFactory,
    protected readonly Connection $database,
    protected readonly TimeInterface $time,
  ) {
    $this->logger = $loggerFactory->get('assessments');
  }

  /**
   * Migrates all assessment webform submissions.
   *
   * @return array{migrated: int, skipped: int, failed: int}
   *   Count of migrated, skipped, and failed submissions.
   */
  public function migrateAll(): array {
    $storage = $this->entityTypeManager->getStorage('webform_submission');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('webform_id', 'assessment')
      ->sort('sid')
      ->execute();

    $migrated = 0;
    $skipped = 0;
    $failed = 0;

    foreach ($storage->loadMultiple($ids) as $submission) {
      try {
        if ($this->migrateSubmission($submission)) {
          $migrated++;
          $this->logger->info('Migrated webform submission @sid.', [
            '@sid' => $submission->id(),
          ]);
        }
        else {
          $skipped++;
        }
      }
      catch (\Exception $e) {
        $failed++;
        $this->logger->error('Failed to migrate webform submission @sid: @error', [
          '@sid' => $submission->id(),
          '@error' => $e->getMessage(),
        ]);
      }
    }

    return ['migrated' => $migrated, 'skipped' => $skipped, 'failed' => $failed];
  }

  /**
   * Migrates a single webform submission into an assessment report.
   *
   * @return bool
   *   TRUE if the submission was migrated, FALSE if it was skipped.
   */
  protected function migrateSubmission(WebformSubmissionInterface $submission): bool {
    $data = $submission->getData();

    // Skip submissions with empty or unknown provider type.
    $typeLabel = $data['public_sector_body'] ?? '';
    if (empty($typeLabel) || !isset(self::PROVIDER_TYPE_MAP[$typeLabel])) {
      $this->logger->notice('Skipping webform submission @sid: empty or unknown provider type "@type".', [
        '@sid' => $submission->id(),
        '@type' => $typeLabel,
      ]);
      return FALSE;
    }

    $ownerId = (int) $submission->getOwnerId();
    $provider = $this->createProvider($data, $ownerId);
    $expressions = $this->createExpressions($data, $ownerId);
    $publicServices = $this->createPublicServices($data, $ownerId);
    $participations = $this->createParticipation($data, $ownerId);
    $assets = $this->getAssets($data, $ownerId);
    $bindingRequirements = $this->createBindingRequirements(
      $data,
      $expressions,
      $publicServices,
      $participations,
      $assets,
    );
    if ($submission->isDraft()) {
      $state = 'draft';
    }
    elseif ($submission->isCompleted()) {
      // isLocked() is always true alongside isCompleted() because the webform
      // locks submissions automatically on submit. There is therefore no
      // separate isLocked branch needed.
      // Note: as agreed, under review submissions will be migrated as
      // published so that they can be edited by moderators.
      $state = 'published';
    }
    else {
      $this->logger->notice('Skipping webform submission @sid: unknown submission state.', [
        '@sid' => $submission->id(),
      ]);
      return FALSE;
    }

    $reportValues = [
      'type' => 'report',
      'name' => !empty($data['short_title']) ? $data['short_title'] : 'Assessment #' . $submission->id(),
      'provider' => $provider,
      'binding_requirement' => $bindingRequirements,
      'state' => $state,
      'status' => $state === 'published',
      'uid' => $ownerId,
    ];

    // Relevant reports (only if "Yes").
    if (($data['previous_assessments'] ?? '') === 'Yes') {
      $links = [];
      foreach ((array) ($data['reference_of_the_linked_assessment'] ?? []) as $reference) {
        if (!empty($reference)) {
          $links[] = ['uri' => $reference, 'title' => ''];
        }
      }
      if ($links) {
        $reportValues['relevant_report'] = $links;
      }
    }

    // Remaining barriers (only if "Yes").
    if (($data['remaining_barriers'] ?? '') === 'Yes' && !empty($data['remaining_barriers_description'])) {
      $reportValues['remaining_barriers'] = [
        'value' => $this->htmlToPlainText($data['remaining_barriers_description']),
        'format' => 'plain_text',
      ];
    }

    // Other comment.
    if (!empty($data['comments'])) {
      $comment = is_array($data['comments'])
        ? ($data['comments']['value'] ?? '')
        : $data['comments'];
      $reportValues['other_comment'] = $this->htmlToPlainText($comment);
    }

    // Documents.
    $documentFids = (array) ($data['upload_assessment_documents'] ?? []);
    if ($documentFids) {
      $reportValues['documents'] = $this->entityTypeManager->getStorage('file')->loadMultiple($documentFids);
    }
    $report = $this->getAssessmentStorage()->create($reportValues);

    // Add statements.
    $statementStorage = $this->entityTypeManager->getStorage('assessment_statement');
    foreach (['legal', 'organisational', 'semantic', 'technical'] as $type) {
      $statementData = $data["identified_effects_cross_border_{$type}"] ?? NULL;
      if (empty($statementData)) {
        continue;
      }
      // Webform composite with #multiple: false still wraps data in [0 => []].
      if (isset($statementData[0]) && is_array($statementData[0])) {
        $statementData = $statementData[0];
      }
      $interpretation = $statementData['legal_options'] ?? NULL;
      $judgement = $statementData['organisational_options'] ?? NULL;

      if (empty($interpretation) && empty($judgement)) {
        continue;
      }

      $statementValues = [
        'type' => $type,
      ];

      if (!empty($interpretation) && isset(self::INTERPRETATION_MAP[$interpretation])) {
        $statementValues['interpretation'] = $this->vocabularyHelper->getTermIdFromIdentifier(
          'assess_statement_interpretation',
          self::INTERPRETATION_MAP[$interpretation],
        );
      }

      if (!empty($judgement)) {
        $statementValues['judgement'] = $this->htmlToPlainText($judgement);
      }

      $report->{$type} = $statementStorage->create($statementValues);
    }

    $report->save();

    // Record the migration mapping for comparison tooling.
    $this->database->merge('assessments_migration_map')
      ->keys(['sid' => (int) $submission->id()])
      ->fields([
        'report_id' => (int) $report->id(),
        'migrated' => $this->time->getRequestTime(),
      ])
      ->execute();

    return TRUE;
  }

  /**
   * Creates or finds a provider public organisation.
   *
   * @return \Drupal\assessments\Entity\PublicOrganisation|int
   *   The provider entity or its ID.
   */
  protected function createProvider(array $data, int $ownerId): PublicOrganisation|int {
    $typeLabel = $data['public_sector_body'] ?? '';
    $typeIdentifier = self::PROVIDER_TYPE_MAP[$typeLabel] ?? NULL;

    if (!$typeIdentifier) {
      throw new \RuntimeException("Unknown provider type: '$typeLabel'");
    }

    $typeTid = $this->vocabularyHelper->getTermIdFromIdentifier('assess_provider_type', $typeIdentifier);
    $name = $data['organisation'] ?? '';
    $isEuropean = $this->isEuropeanOrganisationType($typeIdentifier);

    $countryTid = NULL;
    if (!$isEuropean && !empty($data['country'])) {
      $countryName = self::COUNTRY_NAME_MAP[$data['country']] ?? $data['country'];
      $countryTid = $this->vocabularyHelper->getTermIdFromIdentifier('spatial', $countryName, 'name');
    }

    // Try to find an existing matching organisation.
    $query = $this->getAssessmentStorage()->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'public_organisation')
      ->condition('organisation_type', $typeTid);

    if (!$isEuropean && $countryTid !== NULL) {
      $query->condition('country', $countryTid);
    }

    $ids = $query->execute();
    $existing = $this->findOrganisationByName($name, $ids);
    if ($existing) {
      return $existing;
    }

    // Create a new organisation.
    $values = [
      'type' => 'public_organisation',
      'name' => $name,
      'organisation_type' => $typeTid,
      'status' => TRUE,
      'uid' => $ownerId,
    ];

    if ($countryTid !== NULL) {
      $values['country'] = $countryTid;
    }

    return $this->getAssessmentStorage()->create($values);
  }

  /**
   * Creates expression entities from webform data.
   *
   * @return list<\Drupal\assessments\AssessmentInterface>
   *   Expression entities.
   */
  protected function createExpressions(array $data, int $ownerId): array {
    $expressions = [];

    foreach ((array) ($data['initiatives'] ?? []) as $initiative) {
      if (empty($initiative['initiative'])) {
        continue;
      }

      $values = [
        'type' => 'expression',
        'initiative_name' => $initiative['initiative'],
        'status' => TRUE,
        'uid' => $ownerId,
      ];

      if (!empty($initiative['initiative_link'])) {
        $values['documented_in'] = [
          'uri' => $initiative['initiative_link'],
          'title' => '',
        ];
      }

      $expressions[] = $this->getAssessmentStorage()->create($values);
    }

    return $expressions;
  }

  /**
   * Creates public service entities from webform data.
   *
   * @return list<\Drupal\assessments\AssessmentInterface>
   *   Public service entities.
   */
  protected function createPublicServices(array $data, int $ownerId): array {
    $serviceNames = array_values(array_filter((array) ($data['trans_european_digital_public_service_concerned'] ?? [])));

    // Resolve thematic areas.
    $thematicAreaTids = [];
    $selectedAreas = array_filter((array) ($data['concerned_application_domain_sector'] ?? []));
    $mappedIdentifiers = [];
    foreach ($selectedAreas as $areaLabel) {
      $identifier = self::THEMATIC_AREA_MAP[$areaLabel] ?? NULL;
      if ($identifier && !in_array($identifier, $mappedIdentifiers, TRUE)) {
        $mappedIdentifiers[] = $identifier;
        $thematicAreaTids[] = $this->vocabularyHelper->getTermIdFromIdentifier('assess_public_service_area', $identifier);
      }
    }

    // Resolve countries from geographical_origin.
    $countryTids = $this->resolveGeographicalOriginCountries($data);

    // If no specific service names were given (e.g. the "Potentially many"
    // scope was selected), create one placeholder service to preserve the
    // thematic area data, leaving the name empty.
    if (empty($serviceNames)) {
      if (empty($thematicAreaTids)) {
        return [];
      }
      $serviceNames = [''];
    }

    $services = [];
    foreach ($serviceNames as $serviceName) {
      $services[] = $this->getAssessmentStorage()->create([
        'type' => 'public_service',
        'name' => $serviceName,
        'thematic_area' => $thematicAreaTids,
        'countries' => $countryTids,
        'status' => TRUE,
        'uid' => $ownerId,
      ]);
    }

    return $services;
  }

  /**
   * Creates participation entities from webform data.
   *
   * @return list<\Drupal\assessments\AssessmentInterface>
   *   Participation entities.
   */
  protected function createParticipation(array $data, int $ownerId): array {
    // Resolve participant types.
    $participantTypeTids = [];
    $selectedTypes = array_filter((array) ($data['data_processed_by'] ?? []));
    foreach ($selectedTypes as $typeLabel) {
      $typeLabel = trim($typeLabel);
      $identifier = self::PARTICIPANT_TYPE_MAP[$typeLabel] ?? NULL;
      if ($identifier) {
        $participantTypeTids[] = $this->vocabularyHelper->getTermIdFromIdentifier('assess_participant_type', $identifier);
      }
    }

    // Resolve stakeholder countries from geographical_origin.
    $countryTids = $this->resolveGeographicalOriginCountries($data);

    // Check for "All EU" in geographical origins.
    $hasAllEu = FALSE;
    foreach ((array) ($data['geographical_origin'] ?? []) as $item) {
      if (($item['geographical_origin'] ?? '') === 'All EU') {
        $hasAllEu = TRUE;
        break;
      }
    }

    // Nothing to migrate if no participant types and no country data at all.
    if (empty($participantTypeTids) && empty($countryTids) && !$hasAllEu) {
      return [];
    }

    $participationValues = [
      'type' => 'participation',
      'participant_type' => $participantTypeTids,
      'stakeholder_country' => $countryTids,
      'status' => TRUE,
      'uid' => $ownerId,
    ];

    if ($hasAllEu) {
      $participationValues['eu_countries'] = TRUE;
    }

    return [$this->getAssessmentStorage()->create($participationValues)];
  }

  /**
   * Loads existing solution node entities referenced in the webform.
   *
   * @return list<\Drupal\joinup_solution\Entity\SolutionInterface>
   *   Solution entities.
   */
  protected function getAssets(array $data, int $ownerId): array {
    $nodeStorage = $this->entityTypeManager->getStorage('node');
    $assets = [];

    // Portal solutions (entity autocomplete references by nid).
    foreach ((array) ($data['portal_solutions'] ?? []) as $nid) {
      if (empty($nid)) {
        continue;
      }
      $solution = $nodeStorage->load($nid);
      if ($solution) {
        $assets[] = $solution;
      }
    }

    // External solution links → create new solution nodes.
    foreach ((array) ($data['portal_solutions_link'] ?? []) as $linkData) {
      if (empty($linkData['title']) && empty($linkData['url'])) {
        continue;
      }

      $title = !empty($linkData['title']) ? $linkData['title'] : ($linkData['url'] ?? 'Untitled solution');
      $solutionCollector = $this->entityRepository->loadEntityByUuid('node', AssessmentInterface::SOLUTION_COLLECTOR);

      $solution = $nodeStorage->create([
        'type' => 'solution',
        'title' => $title,
        'og_audience' => $solutionCollector,
        'landing_page' => $linkData['url'] ?? '',
        'field_state' => 'published',
        'uid' => $ownerId,
      ]);
      $solution->save();
      $assets[] = $solution;
    }

    return $assets;
  }

  /**
   * Creates binding requirement paragraphs.
   *
   * @return list<\Drupal\paragraphs\ParagraphInterface>
   *   Binding requirement paragraphs.
   */
  protected function createBindingRequirements(
    array $data,
    array $expressions,
    array $publicServices,
    array $participations,
    array $assets,
  ): array {
    $paragraphStorage = $this->entityTypeManager->getStorage('paragraph');
    $bindingRequirements = (array) ($data['binding_requirement_assessed'] ?? []);
    $docFids = (array) ($data['binding_requirement_documents'] ?? []);

    // Shared values applied to every binding requirement paragraph.
    $sharedValues = [
      'type' => 'binding_requirement',
      'expression' => $expressions,
      'affects' => $publicServices,
      'has_participation' => $participations,
      'asset' => $assets,
    ];

    $paragraphs = [];
    foreach ($bindingRequirements as $requirement) {
      $values = $sharedValues;

      if ($description = $requirement['description_1'] ?? '') {
        $values['description'] = [
          'value' => $description,
          'format' => 'plain_text',
        ];
      }

      // Legal reference as documented_in link.
      if (!empty($requirement['legal_reference'])) {
        $values['documented_in'] = [
          'uri' => $requirement['legal_reference'],
          'title' => '',
        ];
      }

      $paragraph = $paragraphStorage->create($values);

      if ($docFids) {
        $paragraph->set('requirement_documents', $this->entityTypeManager->getStorage('file')->loadMultiple($docFids));
      }

      $paragraphs[] = $paragraph;
    }

    // If no specific requirements were listed but the submission identifies
    // that binding requirements exist (via the concern field), create a
    // placeholder paragraph to preserve the associated shared data
    // (expressions, services, etc.) that would otherwise be lost.
    if (empty($paragraphs) && !empty($data['binding_requirements_identified_concern'])) {
      $paragraph = $paragraphStorage->create($sharedValues);

      if ($docFids) {
        $paragraph->set('requirement_documents', $this->entityTypeManager->getStorage('file')->loadMultiple($docFids));
      }

      $paragraphs[] = $paragraph;
    }

    return $paragraphs;
  }

  /**
   * Resolves geographical_origin composite field to country term IDs.
   *
   * @return list<string>
   *   Country term IDs.
   */
  protected function resolveGeographicalOriginCountries(array $data): array {
    $countryTids = [];
    $seen = [];

    foreach ((array) ($data['geographical_origin'] ?? []) as $item) {
      $countryName = $item['geographical_origin'] ?? '';
      if (empty($countryName) || isset($seen[$countryName])) {
        continue;
      }
      $seen[$countryName] = TRUE;

      if ($countryName === 'All EU') {
        // Use the EU spatial term UUID constant.
        $term = $this->entityRepository->loadEntityByUuid('taxonomy_term', AssessmentInterface::EU_COUNTRY_TERM);
        if ($term) {
          $countryTids[] = $term->id();
        }
        continue;
      }

      try {
        $mappedName = self::COUNTRY_NAME_MAP[$countryName] ?? $countryName;
        $countryTids[] = $this->vocabularyHelper->getTermIdFromIdentifier('spatial', $mappedName, 'name');
      }
      catch (\InvalidArgumentException $e) {
        $this->logger->warning('Could not resolve country "@name": @error', [
          '@name' => $countryName,
          '@error' => $e->getMessage(),
        ]);
      }
    }

    return $countryTids;
  }

  /**
   * Returns and statically caches the assessment entity storage.
   */
  protected function getAssessmentStorage(): ContentEntityStorageInterface {
    if (!isset($this->assessmentStorage)) {
      $storage = $this->entityTypeManager->getStorage('assessment');
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
   * Converts basic HTML to structured plain text.
   *
   * Supports paragraphs, lists, links (extracts URL), and line breaks.
   */
  protected function htmlToPlainText(string $html): string {
    // Normalize line endings.
    $text = str_replace(["\r\n", "\r"], "\n", $html);

    // Replace <br> variants with newlines.
    $text = preg_replace('#<br\s*/?>#i', "\n", $text);

    // Extract URLs from anchor tags.
    $text = preg_replace('#<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>.*?</a>#is', '$1', $text);

    // Convert list items to "- item\n".
    $text = preg_replace('#<li[^>]*>\s*#i', '- ', $text);
    $text = preg_replace('#\s*</li>#i', "\n", $text);

    // Convert closing block tags to double newlines.
    $text = preg_replace('#</(?:p|div|h[1-6])>#i', "\n\n", $text);

    // Remove opening block tags.
    $text = preg_replace('#<(?:p|div|h[1-6]|ul|ol|/ul|/ol)[^>]*>#i', '', $text);

    // Strip any remaining tags.
    $text = strip_tags($text);

    // Decode HTML entities.
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    // Collapse 3+ consecutive newlines into 2.
    $text = preg_replace("#\n{3,}#", "\n\n", $text);

    return trim($text);
  }

}
