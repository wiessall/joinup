@api @group-clone
Feature: Solution language field contains only EU languages.
  Test that solution edit forms show only allowed EU languages in the language field after deploy hook cleanup.

  @update:joinup_solution_deploy_200600
  Scenario Outline: Verify non-EU languages are removed from selected values after deploy hook
    Given I am logged in as a moderator
    When I go to "<solution_edit_path>"
    Then the "Language availability" tagify field should not contain "<excluded_language>"
    And the "Language availability" tagify field should contain "<expected_language>"

    Examples:
      | solution_edit_path                                                                                           | expected_language | excluded_language |
      | /collection/eu-semantic-interoperability-catalogue/solution/agrovoc-thesaurus/edit                           | English           | Arabic            |
      | /collection/eidentity-and-esignature/solution/midpoint/edit                                                  | German            | Japanese          |
