@api @group-clone
Feature: Test migration of collections/solutions to node.

  @update:joinup_rdf_deploy_200008
  Scenario: References embedded into rich text blob fields.

    # Links inside comments.
    Given I visit "/collection/semantic-interoperability-community-semic/solution/statdcat-application-profile-data-portals-europe/discussion/link-visualisation"
    When I click "Draft 4"
    Then I should see "StatDCAT-AP - Draft 4 0.4"

    # Links inside discussions.
    Given I visit "/collection/semic-support-centre/solution/core-public-service-vocabulary/discussion/wrong-version-vocabulary-online"
    When I click "this page"
    Then I should see "Core Public Service Vocabulary 1.01"
    When I visit "/collection/semic-support-centre/solution/dcat-application-profile-data-portals-europe/discussion/provide-your-use-case-and-user-scenario"
    And I click "1st virtual meeting"
    Then I should see "DCAT Application Profile for Data Portals in Europe - Draft 1 0.01"
    When I move backward one page
    And I click "1st virtual meeting"
    Then I should see "DCAT Application Profile for Data Portals in Europe - Draft 1 0.01"

    # Links inside node description fields.
    Given I visit "/collection/european-interoperability-reference-architecture-eira/solution/eira/release/v210"
    When I click "EIRA v2.1.0 Overview.pdf"
    Then I should see "EIRA_v2_1_0_Overview.pdf"

    # Links in events agenda.
    Given I visit "/collection/joinup/event/2nd-cesar-workshop-reaping-benefits-europes-collections-reusable-interoperability-assets"
    When I click "Greek Interoperability Catalogue"
    Then I should see the heading "Greek Interoperability Catalogue"

    # Links in paragraph field_body.
    Given I visit "/collection/open-source-observatory-osor/document/joinup-open-source-news-service-december-2015"
    When the page should contain the html text '<a href="/community/osor/">Open Source Observatory</a>'
    Then I visit "/community/osor"
    Then I should see the heading "Open Source Observatory (OSOR)"
    When I visit "/collection/e-government-innovation-center-egiz/news/moa-id-release-310-veroffen"
    And I click "https://joinup.ec.europa.eu/release/moa-id-310"
    Then I should see "MOA-ID 3.1.0"
    When I visit "/collection/semic-support-centre/news/presentation-adms-1"
    And I click "ADMS"
    Then I should see "Asset Description Metadata Schema (ADMS)"
    When I visit "/collection/nifo-national-interoperability-framework-observatory/solution/eif-toolbox/solution-asset-description-metadata-schema-adms"
    And I click "working group of experts"
    Then I should see "Asset Description Metadata Schema (ADMS)"
    Then I should see the heading "Members"
