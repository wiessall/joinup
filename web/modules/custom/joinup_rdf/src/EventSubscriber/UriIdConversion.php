<?php

declare(strict_types=1);

namespace Drupal\joinup_rdf\EventSubscriber;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\joinup_rdf\Entity\RdfSyncEntityInterface;
use Drupal\sparql_entity_storage\Event\InboundValueEvent;
use Drupal\sparql_entity_storage\Event\OutboundValueEvent;
use Drupal\sparql_entity_storage\Event\SparqlEntityStorageEvents;
use Drupal\sparql_entity_storage\Event\ValueEventBase;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Reacts to entity ID inbound/outbound value.
 *
 * This is a temporary solution needed while converting entities with RDF
 * storage to entities with default Drupal storage. During this process, there
 * are entities still not converted, thus their entity reference fields are
 * stored as URIs in the RDF triplestore. But the target entities were already
 * converted to use the MySQL backend and their IDs are numeric (nodes, terms).
 * We listen to the entity reference field target_id being loaded or saved, and
 * perform the necessary conversion, from URI to numeric ID or vice-versa.
 *
 * @todo Drop in ISAICP-8376
 */
class UriIdConversion implements EventSubscriberInterface {

  /**
   * Fields that should allow TID <-> URI conversion.
   *
   * @const string[]
   */
  private const MAP = [
    'eif_wrapper:recommendation:term',
    'rdf_entity:asset_distribution:field_ad_format',
    'rdf_entity:asset_distribution:field_ad_licence',
    'rdf_entity:asset_distribution:field_ad_repr_technique',
    'rdf_entity:asset_distribution:field_status',
    'rdf_entity:asset_release:field_isr_language',
    'rdf_entity:asset_release:field_spatial_coverage',
    'rdf_entity:asset_release:field_status',
    'rdf_entity:asset_release:field_topic',
    'rdf_entity:collection:field_spatial_coverage',
    'rdf_entity:collection:field_topic',
    'rdf_entity:owner:field_owner_type',
    'rdf_entity:owner:field_owner_type',
    'rdf_entity:solution:field_is_actor',
    'rdf_entity:solution:field_is_business_process',
    'rdf_entity:solution:field_is_eif_recommendation',
    'rdf_entity:solution:field_is_language',
    'rdf_entity:solution:field_is_product_type',
    'rdf_entity:solution:field_is_solution_type',
    'rdf_entity:solution:field_is_standardization_level',
    'rdf_entity:solution:field_is_test_resource_type',
    'rdf_entity:solution:field_is_test_resource_type',
    'rdf_entity:solution:field_spatial_coverage',
    'rdf_entity:solution:field_status',
    'rdf_entity:solution:field_topic',
    'rdf_meta_entity:eif_perspective:eif_layer',
    'rdf_meta_entity:eif_perspective:eif_model',
    'rdf_meta_entity:eif_perspective:eif_principle',
    'rdf_meta_entity:eif_perspective:field_eif_solution_type',
  ];

  /**
   * Static cache for the URI <-> ID relation.
   *
   * Keys are URIs, values are tuples where the first element (0) is the entity
   * type ID and the second element (1) is the entity ID.
   *
   * @var array[]
   */
  private array $uriToId = [];

  /**
   * Constructs a new event subscriber instance.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager service.
   */
  public function __construct(
    private readonly Connection $database,
    private readonly EntityFieldManagerInterface $entityFieldManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      SparqlEntityStorageEvents::INBOUND_VALUE => 'onLoad',
      SparqlEntityStorageEvents::OUTBOUND_VALUE => 'onSave',
    ];
  }

  /**
   * Listens to a value coming from RDF storage to Drupal entity/field API.
   *
   * @param \Drupal\sparql_entity_storage\Event\InboundValueEvent $event
   *   The event.
   */
  public function onLoad(InboundValueEvent $event): void {
    if ($this->acceptsConversion($event)) {
      $event->setValue($this->getId($event->getValue()));
    }
  }

  /**
   * Listens to a value being saved from Drupal entity/field API to RDF storage.
   *
   * @param \Drupal\sparql_entity_storage\Event\OutboundValueEvent $event
   *   The event.
   */
  public function onSave(OutboundValueEvent $event): void {
    if ($this->acceptsConversion($event)) {
      $storageDefinition = $this->entityFieldManager->getFieldStorageDefinitions($event->getEntityTypeId())[$event->getField()];
      $targetEntityTypeId = $storageDefinition->getSetting('target_type');
      $event->setValue($this->getUri($targetEntityTypeId, $event->getValue()));
    }
  }

  /**
   * Returns and caches an entity ID give its corresponding URI.
   *
   * @param string $uri
   *   The URI.
   *
   * @return string|int|null
   *   The entity ID.
   *
   * @throws \InvalidArgumentException
   *   If there's no entity mapped with the passed URI.
   */
  protected function getId(string $uri): string|int|null {
    if (!isset($this->uriToId[$uri])) {
      $result = $this->database->select('rdf_sync_uri')
        ->fields('rdf_sync_uri', ['entity_type', 'entity_id'])
        ->condition('uri', $uri)
        ->execute()
        ->fetch(\PDO::FETCH_NUM) ?: NULL;
      // @todo Remove this temporary exception when ISAICP-8579 lands.
      // @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-8579
      if (!$result && !isset(self::ISAICP_8579[$uri])) {
        throw new \InvalidArgumentException("Can't find a mapped entity with URI $uri");
      }
      $this->uriToId[$uri] = $result;
    }
    return $this->uriToId[$uri][1];
  }

  /**
   * Returns and caches a URI given its corresponding entity ID.
   *
   * @param string $entityTypeId
   *   The entity type ID.
   * @param int|string|\Drupal\joinup_rdf\Entity\RdfSyncEntityInterface $entity
   *   The entity as entity instance or as entity ID.
   *
   * @return string|null
   *   The URI.
   *
   * @throws \InvalidArgumentException
   *   If there's no mapped URI for the passed entity.
   */
  protected function getUri(string $entityTypeId, int|string|RdfSyncEntityInterface $entity): ?string {
    $id = $entity instanceof RdfSyncEntityInterface ? (string) $entity->id() : (string) $entity;
    if (($uri = array_search([$entityTypeId, $id], $this->uriToId, TRUE)) === FALSE) {
      $uri = $this->database->select('rdf_sync_uri')
        ->fields('rdf_sync_uri', ['uri'])
        ->condition('entity_type', $entityTypeId)
        ->condition('entity_id', $id)
        ->execute()
        ->fetchField() ?: NULL;
      if (!$uri) {
        // @todo Re-enable the exception throwing when ISAICP-8579 lands.
        // @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-8579
        // phpcs:disable
        // throw new \InvalidArgumentException("Can't find a URI for $entityTypeId entity with ID $id");
        // phpcs:enable
      }
      // Cache this match.
      $this->uriToId[$uri] = [$entityTypeId, $id];
    }
    return $uri;
  }

  /**
   * Checks if a value qualifies for conversion.
   *
   * @param \Drupal\sparql_entity_storage\Event\ValueEventBase $event
   *   The event.
   *
   * @return bool
   *   Whether to convert the value passed via $event.
   */
  private function acceptsConversion(ValueEventBase $event): bool {
    return in_array("{$event->getEntityTypeId()}:{$event->getBundle()}:{$event->getField()}", self::MAP, TRUE);
  }

  /**
   * URIs excepted from conversion.
   *
   * Temporary list of URIs excepted from conversion. These are references to
   * EIRA terms that doesn't exist.
   *
   * @todo Remove the list when ISAICP-8579 lands.
   * @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-8579
   */
  private const ISAICP_8579 = [
    'http://data.europa.eu/dr8/APIApplicationInterface' => TRUE,
    'http://data.europa.eu/dr8/APIDiscoveryandCatalogueApplicationService' => TRUE,
    'http://data.europa.eu/dr8/APIManagerApplicationService' => TRUE,
    'http://data.europa.eu/dr8/AccessManagementApplicationComponent' => TRUE,
    'http://data.europa.eu/dr8/AccessManagementApplicationService' => TRUE,
    'http://data.europa.eu/dr8/AccessManagementComponent' => TRUE,
    'http://data.europa.eu/dr8/AccessManagementService' => TRUE,
    'http://data.europa.eu/dr8/AchieveInteroperabilityGoal' => TRUE,
    'http://data.europa.eu/dr8/AchieveSemanticInteroperabilityGoal' => TRUE,
    'http://data.europa.eu/dr8/AchieveTechnicalInteroperabilityGoal' => TRUE,
    'http://data.europa.eu/dr8/AdaptiveConfigurationBusinessInterface' => TRUE,
    'http://data.europa.eu/dr8/AdministrationAndMonitoringService' => TRUE,
    'http://data.europa.eu/dr8/AdministrationComponent' => TRUE,
    'http://data.europa.eu/dr8/AdministrativeSimplification' => TRUE,
    'http://data.europa.eu/dr8/AgendaManagementApplicationComponent' => TRUE,
    'http://data.europa.eu/dr8/AgreementOnDataSharingContract' => TRUE,
    'http://data.europa.eu/dr8/AnalyticalCapacityValue' => TRUE,
    'http://data.europa.eu/dr8/AnalyticsEnablersGrouping' => TRUE,
    'http://data.europa.eu/dr8/AnalyticsTechnologyService' => TRUE,
    'http://data.europa.eu/dr8/ApplicationPresentationEnablersGrouping' => TRUE,
    'http://data.europa.eu/dr8/ArchitectureBuildingBlock' => TRUE,
    'http://data.europa.eu/dr8/ArchitecturePrinciple' => TRUE,
    'http://data.europa.eu/dr8/ArchitectureRequirement' => TRUE,
    'http://data.europa.eu/dr8/ArtificialIntelligenceApplicationComponent' => TRUE,
    'http://data.europa.eu/dr8/ArtificialIntelligenceEnablersGrouping' => TRUE,
    'http://data.europa.eu/dr8/AudiovisualService' => TRUE,
    'http://data.europa.eu/dr8/AuditApplicationService' => TRUE,
    'http://data.europa.eu/dr8/AuditComponent' => TRUE,
    'http://data.europa.eu/dr8/AuditService' => TRUE,
    'http://data.europa.eu/dr8/AuthenticationApplicationService' => TRUE,
    'http://data.europa.eu/dr8/AuthenticationMiddlewareApplicationComponent' => TRUE,
    'http://data.europa.eu/dr8/AuthorisationApplicationService' => TRUE,
    'http://data.europa.eu/dr8/BaseRegistryData' => TRUE,
    'http://data.europa.eu/dr8/BaseRegistryDataObject' => TRUE,
    'http://data.europa.eu/dr8/BindingInstrument' => TRUE,
    'http://data.europa.eu/dr8/BlockchainApplicationComponent' => TRUE,
    'http://data.europa.eu/dr8/BlockchainApplicationService' => TRUE,
    'http://data.europa.eu/dr8/BlockchainEnablersGrouping' => TRUE,
    'http://data.europa.eu/dr8/BlockchainInfrastructureEnablers' => TRUE,
    'http://data.europa.eu/dr8/BlockchainLedgerTechnologyService' => TRUE,
    'http://data.europa.eu/dr8/Business' => TRUE,
    'http://data.europa.eu/dr8/BusinessAnalyticsService' => TRUE,
    'http://data.europa.eu/dr8/BusinessCapability' => TRUE,
    'http://data.europa.eu/dr8/BusinessInformation' => TRUE,
    'http://data.europa.eu/dr8/BusinessInteligenceTechnologyService' => TRUE,
    'http://data.europa.eu/dr8/BusinessIntelligenceComponent' => TRUE,
    'http://data.europa.eu/dr8/BusinessProcessManagementComponent' => TRUE,
    'http://data.europa.eu/dr8/BusinessProcessManagerTechnologyService' => TRUE,
    'http://data.europa.eu/dr8/BusinessReportingService' => TRUE,
    'http://data.europa.eu/dr8/BusinessRule' => TRUE,
    'http://data.europa.eu/dr8/BusinessStakeholder' => TRUE,
    'http://data.europa.eu/dr8/CapabilityRequirement' => TRUE,
    'http://data.europa.eu/dr8/CapacityOfExchangingBusinessInformation' => TRUE,
    'http://data.europa.eu/dr8/CharacterEncodingScheme' => TRUE,
    'http://data.europa.eu/dr8/ChoreographyService' => TRUE,
    'http://data.europa.eu/dr8/Citizen' => TRUE,
    'http://data.europa.eu/dr8/CodeOfEthicsCompliance' => TRUE,
    'http://data.europa.eu/dr8/CollaborationComponent' => TRUE,
    'http://data.europa.eu/dr8/ComputingInfrastructureEnablers' => TRUE,
    'http://data.europa.eu/dr8/ConfigurationAndSolutionCartographyService' => TRUE,
    'http://data.europa.eu/dr8/ConfigurationAndSolutionCartographyServiceComponent' => TRUE,
    'http://data.europa.eu/dr8/ConfigurationManagement' => TRUE,
    'http://data.europa.eu/dr8/ConformanceTestReport' => TRUE,
    'http://data.europa.eu/dr8/ConformanceTestingApplicationComponent' => TRUE,
    'http://data.europa.eu/dr8/ConformanceTestingApplicationService' => TRUE,
    'http://data.europa.eu/dr8/ContainerRegistryTechnologyService' => TRUE,
    'http://data.europa.eu/dr8/ContainerSystemSoftware' => TRUE,
    'http://data.europa.eu/dr8/ContentManagementComponent' => TRUE,
    'http://data.europa.eu/dr8/ContentManagementService' => TRUE,
    'http://data.europa.eu/dr8/ControlledVocabulary' => TRUE,
    'http://data.europa.eu/dr8/ControlledVocabularyDataObject' => TRUE,
    'http://data.europa.eu/dr8/CoordinationCapacityValue' => TRUE,
    'http://data.europa.eu/dr8/CoreDataModel' => TRUE,
    'http://data.europa.eu/dr8/Data' => TRUE,
    'http://data.europa.eu/dr8/DataAnalyticsApplicationComponent' => TRUE,
    'http://data.europa.eu/dr8/DataCatalogTechnologyService' => TRUE,
    'http://data.europa.eu/dr8/DataEntity' => TRUE,
    'http://data.europa.eu/dr8/DataExchangeApplicationComponent' => TRUE,
    'http://data.europa.eu/dr8/DataExchangeApplicationService' => TRUE,
    'http://data.europa.eu/dr8/DataExchangeComponent' => TRUE,
    'http://data.europa.eu/dr8/DataExchangeEnablersGrouping' => TRUE,
    'http://data.europa.eu/dr8/DataExchangeService' => TRUE,
    'http://data.europa.eu/dr8/DataExtractionTransformationandLoadingApplicationComponent' => TRUE,
    'http://data.europa.eu/dr8/DataExtractionTransformationandLoadingApplicationService' => TRUE,
    'http://data.europa.eu/dr8/DataFormatDataObject' => TRUE,
    'http://data.europa.eu/dr8/DataLevelMapping' => TRUE,
    'http://data.europa.eu/dr8/DataManagementApplicationComponent' => TRUE,
    'http://data.europa.eu/dr8/DataManagementApplicationService' => TRUE,
    'http://data.europa.eu/dr8/DataMapping' => TRUE,
    'http://data.europa.eu/dr8/DataMappingDataObject' => TRUE,
    'http://data.europa.eu/dr8/DataModel' => TRUE,
    'http://data.europa.eu/dr8/DataModelDataObject' => TRUE,
    'http://data.europa.eu/dr8/DataObject' => TRUE,
    'http://data.europa.eu/dr8/DataOwnerBusinessRole' => TRUE,
    'http://data.europa.eu/dr8/DataPersistenceApplicationApplicationService' => TRUE,
    'http://data.europa.eu/dr8/DataPolicy' => TRUE,
    'http://data.europa.eu/dr8/DataPolicyBusinessObject' => TRUE,
    'http://data.europa.eu/dr8/DataPublicationApplicationComponent' => TRUE,
    'http://data.europa.eu/dr8/DataPublicationApplicationService' => TRUE,
    'http://data.europa.eu/dr8/DataPublicationComponent' => TRUE,
    'http://data.europa.eu/dr8/DataPublicationService' => TRUE,
    'http://data.europa.eu/dr8/DataQualityApplicationComponent' => TRUE,
    'http://data.europa.eu/dr8/DataQualityApplicationService' => TRUE,
    'http://data.europa.eu/dr8/DataRepresentation' => TRUE,
    'http://data.europa.eu/dr8/DataSet' => TRUE,
    'http://data.europa.eu/dr8/DataSetCatalogue' => TRUE,
    'http://data.europa.eu/dr8/DataSetCatalogueDataObject' => TRUE,
    'http://data.europa.eu/dr8/DataSetDataObject' => TRUE,
    'http://data.europa.eu/dr8/DataStandard' => TRUE,
    'http://data.europa.eu/dr8/DataStandardCatalogue' => TRUE,
    'http://data.europa.eu/dr8/DataSyntaxMappingCatalogueApplicationComponent' => TRUE,
    'http://data.europa.eu/dr8/DataTransformationComponent' => TRUE,
    'http://data.europa.eu/dr8/DataTransformationService' => TRUE,
    'http://data.europa.eu/dr8/DataValidationComponent' => TRUE,
    'http://data.europa.eu/dr8/DataValidationService' => TRUE,
    'http://data.europa.eu/dr8/DataVirtualizationApplicationComponent' => TRUE,
    'http://data.europa.eu/dr8/DataWarehouseComponent' => TRUE,
    'http://data.europa.eu/dr8/DataWarehouseService' => TRUE,
    'http://data.europa.eu/dr8/DefinitionOfPublicPolicyObjectives' => TRUE,
    'http://data.europa.eu/dr8/DescriptiveMetadata' => TRUE,
    'http://data.europa.eu/dr8/DigitalAgendaGoal' => TRUE,
    'http://data.europa.eu/dr8/DigitalBusinessCapability' => TRUE,
    'http://data.europa.eu/dr8/DigitalGovernancePlanBusinessObject' => TRUE,
    'http://data.europa.eu/dr8/DigitalInclusion' => TRUE,
    'http://data.europa.eu/dr8/DigitalPublicBusinessService' => TRUE,
    'http://data.europa.eu/dr8/DigitalPublicServiceDeliveryBusinessInterface' => TRUE,
    'http://data.europa.eu/dr8/DigitalPublicServiceDeliveryConsumerBusinessRole' => TRUE,
    'http://data.europa.eu/dr8/DigitalPublicServiceDeliveryModelBusinessCollaboration' => TRUE,
    'http://data.europa.eu/dr8/DigitalPublicServiceDeliveryProviderBusinessRole' => TRUE,
    'http://data.europa.eu/dr8/DigitalServiceInfrastructure' => TRUE,
    'http://data.europa.eu/dr8/DigitalSolutionApplicationComponent' => TRUE,
    'http://data.europa.eu/dr8/DigitalSolutionApplicationService' => TRUE,
    'http://data.europa.eu/dr8/DigitalSolutionArtifact' => TRUE,
    'http://data.europa.eu/dr8/DigitalWorkplaceApplicationComponent' => TRUE,
    'http://data.europa.eu/dr8/DigitalisationRoadmapWorkPackage' => TRUE,
    'http://data.europa.eu/dr8/DistributedLedgerDataObject' => TRUE,
    'http://data.europa.eu/dr8/DocumentManagementService' => TRUE,
    'http://data.europa.eu/dr8/E-ArchivingApplicationComponent' => TRUE,
    'http://data.europa.eu/dr8/E-ArchivingApplicationService' => TRUE,
    'http://data.europa.eu/dr8/E-SignatureVerificationAndValidationApplicationService' => TRUE,
    'http://data.europa.eu/dr8/E-archivingComponent' => TRUE,
    'http://data.europa.eu/dr8/E-archivingService' => TRUE,
    'http://data.europa.eu/dr8/E-paymentComponent' => TRUE,
    'http://data.europa.eu/dr8/E-paymentService' => TRUE,
    'http://data.europa.eu/dr8/E-sealCreationService' => TRUE,
    'http://data.europa.eu/dr8/E-sealPreservationService' => TRUE,
    'http://data.europa.eu/dr8/E-sealVerificationAndValidationService' => TRUE,
    'http://data.europa.eu/dr8/E-signatureCreationService' => TRUE,
    'http://data.europa.eu/dr8/E-signaturePreservationService' => TRUE,
    'http://data.europa.eu/dr8/E-signatureVerificationAndValidationService' => TRUE,
    'http://data.europa.eu/dr8/E-timestampCreationService' => TRUE,
    'http://data.europa.eu/dr8/E-timestampVerificationAndValidationService' => TRUE,
    'http://data.europa.eu/dr8/EIRAArchitectureBuildingBlockRequirement' => TRUE,
    'http://data.europa.eu/dr8/EULegislationCompliance' => TRUE,
    'http://data.europa.eu/dr8/EifInteroperabilityLevelBusinessObject' => TRUE,
    'http://data.europa.eu/dr8/EifPrinciple' => TRUE,
    'http://data.europa.eu/dr8/EiraArchitectureBuildingBlock' => TRUE,
    'http://data.europa.eu/dr8/EiraView' => TRUE,
    'http://data.europa.eu/dr8/EiraViewpoint' => TRUE,
    'http://data.europa.eu/dr8/EuropeanLegalInteroperabilityAgreement' => TRUE,
    'http://data.europa.eu/dr8/EuropeanLibraryofArchitecturePrinciplesPrinciple' => TRUE,
    'http://data.europa.eu/dr8/ExchangeOfBusinessInformation' => TRUE,
    'http://data.europa.eu/dr8/ExternalTechnologyService' => TRUE,
    'http://data.europa.eu/dr8/FederatedIdentityProviderTechnologyService' => TRUE,
    'http://data.europa.eu/dr8/FinancialResource' => TRUE,
    'http://data.europa.eu/dr8/FormsManagementApplicationService' => TRUE,
    'http://data.europa.eu/dr8/FormsManagementComponent' => TRUE,
    'http://data.europa.eu/dr8/FormsManagementService' => TRUE,
    'http://data.europa.eu/dr8/FormulationOfPublicPolicyScenarios' => TRUE,
    'http://data.europa.eu/dr8/FrameworkAgreementContract' => TRUE,
    'http://data.europa.eu/dr8/HostingFacility' => TRUE,
    'http://data.europa.eu/dr8/HumanInterface' => TRUE,
    'http://data.europa.eu/dr8/IdentifierScheme' => TRUE,
    'http://data.europa.eu/dr8/IdentityAndAccessInfrastructureEnablers' => TRUE,
    'http://data.europa.eu/dr8/IdentityManagementComponent' => TRUE,
    'http://data.europa.eu/dr8/IdentityManagementService' => TRUE,
    'http://data.europa.eu/dr8/IdentityProviderTechnologyService' => TRUE,
    'http://data.europa.eu/dr8/ImpactAssessment' => TRUE,
    'http://data.europa.eu/dr8/ImplementingGuideline' => TRUE,
    'http://data.europa.eu/dr8/InclusionAndAccessibility' => TRUE,
    'http://data.europa.eu/dr8/IndividualStakeholder' => TRUE,
    'http://data.europa.eu/dr8/InformationBusinessObject' => TRUE,
    'http://data.europa.eu/dr8/Innovation' => TRUE,
    'http://data.europa.eu/dr8/IntegrityVerificationApplicationService' => TRUE,
    'http://data.europa.eu/dr8/InteroperabilityAspect' => TRUE,
    'http://data.europa.eu/dr8/InteroperabilityFramework' => TRUE,
    'http://data.europa.eu/dr8/InteroperabilityFrameworkBusinessObject' => TRUE,
    'http://data.europa.eu/dr8/InteroperabilityGovernance' => TRUE,
    'http://data.europa.eu/dr8/InteroperabilityOrganisationalAuthority' => TRUE,
    'http://data.europa.eu/dr8/InteroperabilityRequirement' => TRUE,
    'http://data.europa.eu/dr8/InteroperabilitySkill' => TRUE,
    'http://data.europa.eu/dr8/InteroperabilitySpecification' => TRUE,
    'http://data.europa.eu/dr8/InteroperabilitySpecificationContract' => TRUE,
    'http://data.europa.eu/dr8/InteroperabilityStrategy' => TRUE,
    'http://data.europa.eu/dr8/InteroperabilityStrategyGoal' => TRUE,
    'http://data.europa.eu/dr8/InteroperableDigitalPublicService' => TRUE,
    'http://data.europa.eu/dr8/InteroperableDigitalPublicServiceImplementationOrientationConstraint' => TRUE,
    'http://data.europa.eu/dr8/InteroperableDigitalPublicServicesImplementationOrientation' => TRUE,
    'http://data.europa.eu/dr8/InteroperableDigitalSolutionApplicationComponent' => TRUE,
    'http://data.europa.eu/dr8/InteroperableEuropeanSolution' => TRUE,
    'http://data.europa.eu/dr8/InteroperableEuropeanSolutionComponent' => TRUE,
    'http://data.europa.eu/dr8/InteroperableEuropeanSolutionGoal' => TRUE,
    'http://data.europa.eu/dr8/InteroperableEuropeanSolutionService' => TRUE,
    'http://data.europa.eu/dr8/KeyInteroperabilityEnabler' => TRUE,
    'http://data.europa.eu/dr8/KeyInteroperabilityEnablers' => TRUE,
    'http://data.europa.eu/dr8/KnowledgeDiscoveryApplicationComponent' => TRUE,
    'http://data.europa.eu/dr8/KnowledgeDiscoveryApplicationService' => TRUE,
    'http://data.europa.eu/dr8/KnowledgeDiscoveryEnablers' => TRUE,
    'http://data.europa.eu/dr8/KnowledgeRepresentation' => TRUE,
    'http://data.europa.eu/dr8/LegalAct' => TRUE,
    'http://data.europa.eu/dr8/LegalActRepresentation' => TRUE,
    'http://data.europa.eu/dr8/LegalActRequirement' => TRUE,
    'http://data.europa.eu/dr8/LegalAgreementContract' => TRUE,
    'http://data.europa.eu/dr8/LegalAgreements-InternationalTreaties' => TRUE,
    'http://data.europa.eu/dr8/LegalInteroperabilityAgreementContract' => TRUE,
    'http://data.europa.eu/dr8/LegalInteroperabilityRequirement' => TRUE,
    'http://data.europa.eu/dr8/LegalInteroperabilitySpecification' => TRUE,
    'http://data.europa.eu/dr8/LegislationCatalogue' => TRUE,
    'http://data.europa.eu/dr8/LegislationCatalogueBusinessObject' => TRUE,
    'http://data.europa.eu/dr8/LifecycleManagementService' => TRUE,
    'http://data.europa.eu/dr8/LinkedDataRepresentation' => TRUE,
    'http://data.europa.eu/dr8/LoadBalancerTechnologyService' => TRUE,
    'http://data.europa.eu/dr8/LoggingService' => TRUE,
    'http://data.europa.eu/dr8/MachineLearningTechnologyService' => TRUE,
    'http://data.europa.eu/dr8/MachineToMachineApplicationInterface' => TRUE,
    'http://data.europa.eu/dr8/MachineToMachineInterface' => TRUE,
    'http://data.europa.eu/dr8/MachineTranslationComponent' => TRUE,
    'http://data.europa.eu/dr8/MachineTranslationService' => TRUE,
    'http://data.europa.eu/dr8/MarketCollaboration' => TRUE,
    'http://data.europa.eu/dr8/MasterData' => TRUE,
    'http://data.europa.eu/dr8/MasterDataObject' => TRUE,
    'http://data.europa.eu/dr8/MasterDataPolicy' => TRUE,
    'http://data.europa.eu/dr8/MessagingService' => TRUE,
    'http://data.europa.eu/dr8/MetadataCatalogueDataObject' => TRUE,
    'http://data.europa.eu/dr8/MetadataDataObject' => TRUE,
    'http://data.europa.eu/dr8/MetadataManagementApplicationComponent' => TRUE,
    'http://data.europa.eu/dr8/MetadataManagementApplicationService' => TRUE,
    'http://data.europa.eu/dr8/MetadataManagementComponent' => TRUE,
    'http://data.europa.eu/dr8/MetadataManagementService' => TRUE,
    'http://data.europa.eu/dr8/MobileAppBusinessInterface' => TRUE,
    'http://data.europa.eu/dr8/Multilingualism' => TRUE,
    'http://data.europa.eu/dr8/Network' => TRUE,
    'http://data.europa.eu/dr8/NetworkingService' => TRUE,
    'http://data.europa.eu/dr8/NewPublicManagementApproach' => TRUE,
    'http://data.europa.eu/dr8/Non-bindingInstrument' => TRUE,
    'http://data.europa.eu/dr8/Non-bindingInstrumentRequirement' => TRUE,
    'http://data.europa.eu/dr8/Ontology' => TRUE,
    'http://data.europa.eu/dr8/OntologyDataObject' => TRUE,
    'http://data.europa.eu/dr8/OpenData' => TRUE,
    'http://data.europa.eu/dr8/OpenDataPolicy' => TRUE,
    'http://data.europa.eu/dr8/OpenDataPolicyBusinessObject' => TRUE,
    'http://data.europa.eu/dr8/OpenDataRepresentation' => TRUE,
    'http://data.europa.eu/dr8/Openness' => TRUE,
    'http://data.europa.eu/dr8/OperationalEnabler' => TRUE,
    'http://data.europa.eu/dr8/OperationalProcedure' => TRUE,
    'http://data.europa.eu/dr8/OrchestrationApplicationComponent' => TRUE,
    'http://data.europa.eu/dr8/OrchestrationComponent' => TRUE,
    'http://data.europa.eu/dr8/OrchestrationService' => TRUE,
    'http://data.europa.eu/dr8/Organisation' => TRUE,
    'http://data.europa.eu/dr8/OrganisationStakeholder' => TRUE,
    'http://data.europa.eu/dr8/OrganisationalInteroperabilityAgreement' => TRUE,
    'http://data.europa.eu/dr8/OrganisationalInteroperabilityAgreementContract' => TRUE,
    'http://data.europa.eu/dr8/OrganisationalInteroperabilityEnabler' => TRUE,
    'http://data.europa.eu/dr8/OrganisationalInteroperabilityRequirement' => TRUE,
    'http://data.europa.eu/dr8/OrganisationalInteroperabilitySpecification' => TRUE,
    'http://data.europa.eu/dr8/OrganisationalPolicy' => TRUE,
    'http://data.europa.eu/dr8/OrganisationalProcedure' => TRUE,
    'http://data.europa.eu/dr8/OrganisationalSkill' => TRUE,
    'http://data.europa.eu/dr8/OrganisationalStructure' => TRUE,
    'http://data.europa.eu/dr8/PKICertificateManagerTechnologyService' => TRUE,
    'http://data.europa.eu/dr8/PartnerManagementComponent' => TRUE,
    'http://data.europa.eu/dr8/PartnerManagementService' => TRUE,
    'http://data.europa.eu/dr8/PrivacyApplicationComponent' => TRUE,
    'http://data.europa.eu/dr8/PrivacyApplicationService' => TRUE,
    'http://data.europa.eu/dr8/PrivacyFrameworkBusinessObject' => TRUE,
    'http://data.europa.eu/dr8/PrivacyPolicyBusinessObject' => TRUE,
    'http://data.europa.eu/dr8/PrivateCloudFacility' => TRUE,
    'http://data.europa.eu/dr8/PrivateHostingFacility' => TRUE,
    'http://data.europa.eu/dr8/PrivateNetwork' => TRUE,
    'http://data.europa.eu/dr8/Proactiveness' => TRUE,
    'http://data.europa.eu/dr8/PublicAdministration' => TRUE,
    'http://data.europa.eu/dr8/PublicAdministrationStakeholder' => TRUE,
    'http://data.europa.eu/dr8/PublicHostingFacility' => TRUE,
    'http://data.europa.eu/dr8/PublicNetwork' => TRUE,
    'http://data.europa.eu/dr8/PublicPolicy' => TRUE,
    'http://data.europa.eu/dr8/PublicPolicyContextConstraint' => TRUE,
    'http://data.europa.eu/dr8/PublicPolicyCourseOfAction' => TRUE,
    'http://data.europa.eu/dr8/PublicPolicyCycle' => TRUE,
    'http://data.europa.eu/dr8/PublicPolicyCycleBusinessProcess' => TRUE,
    'http://data.europa.eu/dr8/PublicPolicyDevelopmentEnabler' => TRUE,
    'http://data.europa.eu/dr8/PublicPolicyEvaluation' => TRUE,
    'http://data.europa.eu/dr8/PublicPolicyFormulationAndImplementationInstrument' => TRUE,
    'http://data.europa.eu/dr8/PublicPolicyImplementation' => TRUE,
    'http://data.europa.eu/dr8/PublicPolicyImplementationMandate' => TRUE,
    'http://data.europa.eu/dr8/PublicPolicyObjectiveGoal' => TRUE,
    'http://data.europa.eu/dr8/PublicServiceAgent' => TRUE,
    'http://data.europa.eu/dr8/PublicServiceCatalogue' => TRUE,
    'http://data.europa.eu/dr8/PublicServiceComponent' => TRUE,
    'http://data.europa.eu/dr8/PublicServiceConsumer' => TRUE,
    'http://data.europa.eu/dr8/PublicServiceDeliveryAgent' => TRUE,
    'http://data.europa.eu/dr8/PublicServiceProvider' => TRUE,
    'http://data.europa.eu/dr8/RecordManagementComponent' => TRUE,
    'http://data.europa.eu/dr8/RecordManagementService' => TRUE,
    'http://data.europa.eu/dr8/ReferenceData' => TRUE,
    'http://data.europa.eu/dr8/RegisteredElectronicDeliveryService' => TRUE,
    'http://data.europa.eu/dr8/RegistrationApplicationService' => TRUE,
    'http://data.europa.eu/dr8/Representation' => TRUE,
    'http://data.europa.eu/dr8/RequestValidationApplicationService' => TRUE,
    'http://data.europa.eu/dr8/Reusability' => TRUE,
    'http://data.europa.eu/dr8/SaasTechnologyService' => TRUE,
    'http://data.europa.eu/dr8/SchemaLevelMapping' => TRUE,
    'http://data.europa.eu/dr8/SecurityAndPrivacyPolicy' => TRUE,
    'http://data.europa.eu/dr8/SecurityFramework' => TRUE,
    'http://data.europa.eu/dr8/SecurityFrameworkBusinessObject' => TRUE,
    'http://data.europa.eu/dr8/SecurityPolicyBusinessObject' => TRUE,
    'http://data.europa.eu/dr8/SemanticAgreementContract' => TRUE,
    'http://data.europa.eu/dr8/SemanticFunctionalContentGrouping' => TRUE,
    'http://data.europa.eu/dr8/SemanticGovernanceContentGrouping' => TRUE,
    'http://data.europa.eu/dr8/SemanticInteroperabilityRequirement' => TRUE,
    'http://data.europa.eu/dr8/SemanticInteroperabilitySpecification' => TRUE,
    'http://data.europa.eu/dr8/Service' => TRUE,
    'http://data.europa.eu/dr8/ServiceDeliveryModel' => TRUE,
    'http://data.europa.eu/dr8/ServiceDiscoveryComponent' => TRUE,
    'http://data.europa.eu/dr8/ServiceDiscoveryService' => TRUE,
    'http://data.europa.eu/dr8/ServiceRegistryComponent' => TRUE,
    'http://data.europa.eu/dr8/SharedInfrastructureContentGrouping' => TRUE,
    'http://data.europa.eu/dr8/SharedKnowledgeBase' => TRUE,
    'http://data.europa.eu/dr8/SharedKnowledgeContentGrouping' => TRUE,
    'http://data.europa.eu/dr8/SharedLegalContentGrouping' => TRUE,
    'http://data.europa.eu/dr8/SharedOrganisationalContentGrouping' => TRUE,
    'http://data.europa.eu/dr8/SharedPlatform' => TRUE,
    'http://data.europa.eu/dr8/SharedPlatformApplicationComponent' => TRUE,
    'http://data.europa.eu/dr8/SmartDevice' => TRUE,
    'http://data.europa.eu/dr8/SocialParticipation' => TRUE,
    'http://data.europa.eu/dr8/Solution' => TRUE,
    'http://data.europa.eu/dr8/SolutionBuildingBlock' => TRUE,
    'http://data.europa.eu/dr8/SolutionBuildingBlockArtifact' => TRUE,
    'http://data.europa.eu/dr8/SolutionSpecification' => TRUE,
    'http://data.europa.eu/dr8/Specification' => TRUE,
    'http://data.europa.eu/dr8/SyntaxEncodingScheme' => TRUE,
    'http://data.europa.eu/dr8/TechnicalAgreementContract' => TRUE,
    'http://data.europa.eu/dr8/TechnicalGovernanceContentGrouping' => TRUE,
    'http://data.europa.eu/dr8/TechnicalInteroperabilityAgreementContract' => TRUE,
    'http://data.europa.eu/dr8/TechnicalInteroperabilityRequirement' => TRUE,
    'http://data.europa.eu/dr8/TechnicalInteroperabilitySpecification' => TRUE,
    'http://data.europa.eu/dr8/TechnicalSpecification' => TRUE,
    'http://data.europa.eu/dr8/TechnologyTechnologyInterface' => TRUE,
    'http://data.europa.eu/dr8/TestEnablersGrouping' => TRUE,
    'http://data.europa.eu/dr8/TransactionalData' => TRUE,
    'http://data.europa.eu/dr8/Transparency' => TRUE,
    'http://data.europa.eu/dr8/Trust' => TRUE,
    'http://data.europa.eu/dr8/TrustEnablersGrouping' => TRUE,
    'http://data.europa.eu/dr8/TrustRegistryComponent' => TRUE,
    'http://data.europa.eu/dr8/TrustRegistryService' => TRUE,
    'http://data.europa.eu/dr8/TrustServiceProvisioningComponent' => TRUE,
    'http://data.europa.eu/dr8/UXManagementApplicationService' => TRUE,
    'http://data.europa.eu/dr8/User-centricity' => TRUE,
    'http://data.europa.eu/dr8/VirtualDatasetDataObject' => TRUE,
    'http://data.europa.eu/dr8/VirtualMachineNode' => TRUE,
    'http://data.europa.eu/dr8/WebPortalBusinessInterface' => TRUE,
    'http://data.europa.eu/dr8/WebServiceApplicationInterface' => TRUE,
  ];

}
