@api @group-clone
Feature: Check documents published on.

  @update:joinup_core_update_200300
  Scenario Outline: Check documents published on.
    Given I am logged in as a user with the "moderator" role
    And I visit "<path>"
    And I should see the text "Published on: <date>"

    Examples:
      | path                                                                                                                              | date       | state     |
      | /collection/cyprus-egovernment-initiatives/document/cyprus-ecollaboration-platform                                                |            | draft     |
      | /collection/rolling-plan-ict-standardisation/document/uitp-international-association-public-transport                             |            | proposed  |
      | /collection/open-source-observatory-osor/document/e-municipalities                                                                | 30/05/2008 | archived  |
      | /collection/governance-board/document/rules-procedure-interoperable-europe-board-adopted-during-its-first-meeting-5-december-2024 | 20/01/2025 | published |
