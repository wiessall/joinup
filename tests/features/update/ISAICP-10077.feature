@api @group-clone
Feature: Assert that releases can be added after deploy.

  @update:joinup_core_deploy_200600
  Scenario Outline:
    Given I am logged in as a moderator
    And I go to "<path>"
    # Response code 200 is for non archived solutions where releases can be
    # added. If the solution is archived, the response code will be 403. In any
    # case, none of them should return a 404.
    Then the response status code should be <response_code>

    Examples:
      | path                                                                                                                                                       | response_code |
      | /collection/ehealth/solution/federated-security-services-maintaining-identities-and-policies-context-healthcare-ap/release/add                             | 200           |
      | /collection/egovernment/solution/oioxml-bank-account/release/add                                                                                           | 200           |
      | /collection/egovernment/solution/oioxml-email-addresses/release/add                                                                                        | 200           |
      | /collection/egovernment/solution/information-directory/release/add                                                                                         | 200           |
      | /collection/egovernment/solution/oioxml-person/release/add                                                                                                 | 200           |
      | /collection/egovernment/solution/oioxml-company-identification/release/add                                                                                 | 200           |
      | /collection/egovernment/solution/naming-and-design-rules-oioxml-ndr-30/release/add                                                                         | 200           |
      | /collection/egovernment/solution/xml-structures-personal-data/release/add                                                                                  | 200           |
      | /collection/egovernment/solution/xml-structures-business-objects/release/add                                                                               | 200           |
      | /collection/employment-and-working-conditions/solution/eures-taxonomy/release/add                                                                          | 200           |
      | /collection/justice-law-and-security/solution/xjustiz/release/add                                                                                          | 200           |
      | /collection/science-and-technology/solution/access-biological-collection-data-abcd/release/add                                                             | 403           |
      | /collection/egovernment/solution/elkat-electronic-service-catalogue-austrian-administration/release/add                                                    | 200           |
      | /collection/egovernment/solution/elak-transactions/release/add                                                                                             | 200           |
      | /collection/egovernment/solution/handbook-diacritics/release/add                                                                                           | 200           |
      | /collection/egovernment/solution/standard-data-e-forms/release/add                                                                                         | 200           |
      | /collection/egovernment/solution/xml-construction-kit/release/add                                                                                          | 200           |
      | /collection/egovernment/solution/xml-electronic-receipt/release/add                                                                                        | 200           |
      | /collection/egovernment/solution/xml-schema-ediakt-ii/release/add                                                                                          | 200           |
      | /collection/egovernment/solution/proven-electronic-delivery/release/add                                                                                    | 200           |
      | /collection/egovernment/solution/xml-definition-personal-binding/release/add                                                                               | 200           |
      | /collection/egovernment/solution/electronic-authorisation/release/add                                                                                      | 200           |
      | /collection/egovernment/solution/service-types-austrian-public-administrations/release/add                                                                 | 200           |
      | /collection/statistics/solution/statistical-core-vocabulary-scovo/release/add                                                                              | 200           |
      | /collection/eidentity-and-esignature/solution/person-information-ontology/release/add                                                                      | 200           |
      | /collection/science-and-technology/solution/access-biological-collection-data-extension-geosciences-abcd-efg/release/add                                   | 200           |
      | /collection/employment-and-working-conditions/solution/europass-xml-schema-v21/release/add                                                                 | 403           |
      | /collection/semic-support-centre/solution/belgian-countrycode-project-business-case/release/add                                                            | 200           |
      | /collection/semic-support-centre/solution/asset-description-metadata-schema-adms/release/add                                                               | 200           |
      | /collection/semic-support-centre/solution/asset-description-metadata-schema-software/release/add                                                           | 403           |
      | /collection/semic-support-centre/solution/radion/release/add                                                                                               | 200           |
      | /collection/registered-organization-vocabulary/solution/core-business-vocabulary/release/add                                                               | 200           |
      | /collection/employment-and-working-conditions/solution/europass-xml-schema-v30/release/add                                                                 | 403           |
      | /collection/justice-law-and-security/solution/e-justice-communication-online-data-exchange/release/add                                                     | 403           |
      | /collection/eidentity-and-esignature/solution/estonian-eid-software/release/add                                                                            | 200           |
      | /collection/simple-procedures-online-cross-border-services-spocs/solution/spocs-simple-procedures-online-crossborder-services/release/add                  | 403           |
      | /collection/eparticipation-and-evoting/solution/eci-validation-tool-statements-support-vteci/release/add                                                   | 403           |
      | /collection/secure-identity-across-borders-linked-stork/solution/stork/release/add                                                                         | 200           |
      | /collection/open-government/solution/open-data-support-software/release/add                                                                                | 403           |
      | /collection/semic-support-centre/solution/core-public-service-vocabulary-application-profile/release/add                                                   | 200           |
      | /collection/e-government-innovation-center-egiz/solution/mocca/release/add                                                                                 | 200           |
      | /collection/e-government-innovation-center-egiz/solution/pdf-over/release/add                                                                              | 200           |
      | /collection/eparticipation-and-evoting/solution/eusurvey/release/add                                                                                       | 200           |
      | /collection/eidentity-and-esignature/solution/signature-verification-portal/release/add                                                                    | 200           |
      | /collection/eidentity-and-esignature/solution/citizen-card-encrypted-cce/release/add                                                                       | 403           |
      | /collection/statistics/solution/relais-record-linkage-istat/release/add                                                                                    | 403           |
      | /collection/eparticipation-and-evoting/solution/eci-online-collection-software-ocs/release/add                                                             | 200           |
      | /collection/e-government-innovation-center-egiz/solution/moa-id/release/add                                                                                | 200           |
      | /collection/european-committee-standardization-cen/solution/cen-business-interoperability-interface-workshop-3/release/add                                 | 403           |
      | /collection/semic-support-centre/solution/dcat-ap-validator/release/add                                                                                    | 403           |
      | /collection/european-committee-standardization-cen/solution/cen-workshop-agreement-global-ebusiness-interoperability-test-beds-gitb-phase-3/release/add    | 403           |
      | /collection/eidentity-and-esignature/solution/certificate-status-tool/release/add                                                                          | 403           |
      | /collection/semic-support-centre/solution/core-public-organisation-vocabulary/release/add                                                                  | 200           |
      | /collection/semic-support-centre/solution/asset-description-metadata-schema-adms-revision/release/add                                                      | 200           |
      | /collection/semic-support-centre/solution/dcat-application-profile-implementation-guidelines/release/add                                                   | 200           |
      | /collection/semic-support-centre/solution/core-criterion-and-core-evidence-vocabulary/release/add                                                          | 200           |
      | /collection/e-government-innovation-center-egiz/solution/moa-spss/release/add                                                                              | 200           |
      | /collection/are3na/solution/re3gistry/release/add                                                                                                          | 200           |
      | /collection/big-data-and-open-knowledge-public-administrations/solution/isa-action-122-providing-big-data-opportunities-public-administrations/release/add | 403           |
      | /collection/eparticipation-and-evoting/solution/isa-action-218-consolidating-and-integrating-reusable-tools-eu-public-participation/release/add            | 403           |
      | /collection/e-government-innovation-center-egiz/solution/pdf/release/add                                                                                   | 200           |
      | /collection/portal/solution/joinup-archive/release/add                                                                                                     | 403           |
      | /collection/interoperability-test-bed-repository/solution/interoperability-test-bed/release/add                                                            | 200           |
      | /collection/ehealth/solution/pharmacy-duties-athens-region/release/add                                                                                     | 403           |
