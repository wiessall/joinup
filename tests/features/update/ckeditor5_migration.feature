@api @group-clone
Feature: Covers EPIC-7409.

  Scenario: CK5 convert old proprietary video embed markup into media (109002).

    Given I am logged in as a moderator
    And I go to the "Interoperability Test Bed" collection
    When I click "Edit" in the "Entity actions" region
    And I should not see the text "Embedded Video (Responsive, autoplaying)."
    And I should not see the text ":new_description"

    And I go to the "Personal Data Spaces" custom page
    When I click "Edit" in the "Entity actions" region
    And I should not see the text "Embedded Video (Responsive, autoplaying)."

    And I go to the "Save the date: Easy sharing of reference data with Re3gistry at the 2023 ENDORSE Conference" event
    When I click "Edit" in the "Entity actions" region
    And I should not see the text "Embedded Video (Responsive, autoplaying)."
