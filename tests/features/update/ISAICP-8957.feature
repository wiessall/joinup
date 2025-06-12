@api @group-clone
Feature: Tests related to ISAICP-8957.

  @update:joinup_core_deploy_200500
  Scenario: Test that keywords were migrated properly.
    Given I am logged in as a moderator
    And I go to the edit form of the "OSPOs & OSS Governance" "custom page"
    # Do a direct check 1. because the keywords in the edit form are including
    # the entity IDs but we do not know them, and 2. because the solution
    # wizard EPIC has introduced fields that are empty by default and required.
    Then the "The Digital Innovation Lab (iLab)" solution should refer to the following keywords:
      | ilab       |
      | digital    |
      | innovation |
      | lab        |
