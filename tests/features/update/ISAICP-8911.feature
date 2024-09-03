@api @group-clone
Feature: Test the converted videos into document.

  @update:joinup_core_update_111200
  Scenario: Videos have migrated correctly.
    When I visit "node/125946"
    Then I should see the heading "eGovernment Reduction of Administrative Burden-ePractice TV interview: Mr Siim Sikkut"
    And I should see the text "Mr Siim Sikkut, Government office of Estonia"
    And I should see the text "Published on: 10/06/2014"
    And I should see the text "Last update: 09/06/2014"

    When I visit "node/125943"
    Then I should see the heading "Concluding Remarks - Terje Peetso, DG CONNECT"
    And I should see the text "Terje Peetso, DG CONNECT - eHealth and the Brain - ICT for Neuropsychiatric Health, 5 November, Brussels"
    And I should see the text "Published on: 20/11/2013"

    When I visit "node/125865"
    Then I should see the heading "EIF workshop-ePractice TV:Soeren Bittins, Massimiliano Massi"
    And I should see the text "EIF workshop, 16 April 2012"
    And I should see the text "Interviewees: Soeren Bittins, Massimiliano Massi, Fraunhofer Fokus"
    And I should see the text "Published on: 11/05/2012"

    When I visit "/community/epractice/video/egov-reduction-admininstrative-burden-epractice-tv-interview-kris-blancke"
    Then I should see the heading "eGov Reduction of Admininstrative Burden-ePractice TV interview: Kris Blancke"
    And I should see the text "Mr Kris Blancke, Chancellery of the Prime Minister, Administrative Simplification Agency (DAV/ASA)"
    And I should see the text "Published on: 10/06/2014"

  @update:joinup_core_update_111200
  Scenario Outline: Ensure old /elibrary/video/* redirects are maintained and are pointing to the new document nodes.
    Given I visit "<source>"
    Then I should be on "<destination>"

    Examples:
      | source                                                                                                | destination                                                                                                                                 |
      | /elibrary/video/us-national-information-exchange-model-niem-explained                                 | /collection/semic-support-centre/document/us-national-information-exchange-model-niem-explained                                             |
      | /elibrary/video/e-codex-e-justice-communication-online-data-exchange                                  | /collection/justice-law-and-security/document/e-codex-e-justice-communication-online-data-exchange                                          |
      | /elibrary/video/eu-declan-deasy-about-digital-single-market                                           | /collection/egovernment/document/eu-declan-deasy-about-digital-single-market                                                                |
      | /elibrary/video/eu-story-explaining-digital-single-market-and-large-scale-pilots                      | /collection/egovernment/document/eu-story-explaining-digital-single-market-and-large-scale-pilots                                           |
      | /elibrary/video/presenting-osepa-project-share-knowledge-and-experience-about-foss-public-administrat | /collection/open-source-observatory-osor/document/presenting-osepa-project-share-knowledge-and-experience-about-foss-public-administrations |
