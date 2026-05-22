@api @group-clone
Feature: Verify that release/add pages work after fixing bare /release aliases.

  @update:joinup_core_deploy_201302
  Scenario Outline: The release add page is reachable for non-archived solutions.
    Given I am logged in as a moderator
    And I go to "<path>"
    Then I should see the heading "Add Release"

    Examples:
      | path                                                                                                                              |
      | /collection/are3na/solution/re3gistry/release/add                                                                                 |
      | /collection/e-government-innovation-center-egiz/solution/moa-spss/release/add                                                     |
      | /collection/e-government-innovation-center-egiz/solution/pdf-over/release/add                                                     |
      | /collection/e-government-innovation-center-egiz/solution/pdf/release/add                                                          |
      | /collection/eparticipation-and-evoting/solution/eci-online-collection-software-ocs/release/add                                    |
      | /collection/eparticipation-and-evoting/solution/eusurvey/release/add                                                              |
      | /collection/interoperability-test-bed-repository/solution/interoperability-test-bed/release/add                                   |
      | /collection/semic-support-centre/solution/asset-description-metadata-schema-adms/release/add                                      |
      | /collection/semic-support-centre/solution/core-criterion-and-core-evidence-vocabulary/release/add                                 |
      | /collection/semic-support-centre/solution/core-public-organisation-vocabulary/release/add                                         |

  @update:joinup_core_deploy_201302
  Scenario Outline: The bare /release alias is gone for archived solutions.
    Given I am an anonymous user
    And I go to "<path>"
    Then the response status code should be 404

    Examples:
      | path                                                                                                                              |
      | /collection/e-government-innovation-center-egiz/solution/moa-id/release                                                           |
      | /collection/e-government-innovation-center-egiz/solution/mocca/release                                                            |
      | /collection/eidentity-and-esignature/solution/signature-verification-portal/release                                               |
      | /collection/portal-support/solution/joinup-archive/release                                                                        |
      | /collection/secure-identity-across-borders-linked-stork/solution/stork/release                                                    |
      | /collection/semic-support-centre/solution/dcat-application-profile-implementation-guidelines/release                              |
