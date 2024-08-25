@api @group-clone
Feature: Test the converted videos into document.

  @update:joinup_core_update_112000
  Scenario: Videos have migrated correctly.
    When I visit "node/125946"
    Then I should see the heading "eGovernment Reduction of Administrative Burden-ePractice TV interview: Mr Siim Sikkut"
    And I should see the text "Mr Siim Sikkut, Government office of Estonia"
    And I should see the text "Last update: 09/06/2014"

    When I visit "node/125943"
    Then I should see the heading "Concluding Remarks - Terje Peetso, DG CONNECT"
    And I should see the text "Terje Peetso, DG CONNECT - eHealth and the Brain - ICT for Neuropsychiatric Health, 5 November, Brussels"
    And I should see the text "Last update: 20/11/2013"

    When I visit "node/125760"
    Then I should see the heading "eEurope Awards 2005"
    And I should see the text "Video of the eEurope Awards 2005."
    And I should see the text "Manchester, November 2005."
    And I should see the text "Last update: 29/07/2007"
