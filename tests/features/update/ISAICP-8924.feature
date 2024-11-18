@api @group-clone
Feature: Test spatial coverage migration.

  @update:joinup_core_update_200100
  Scenario Outline: Check spatial coverage migration
    Given I am logged in as a moderator
    Then I visit "<url>"
    And I click "Edit" in the "Entity actions" region
    Then I check values "<values>" in "Geographical coverage"

    Examples:
      | url                                                                         | values                             |
      | /collection/egovernment/document/document-management-common-module          | Spain (9881)                       |
      | /collection/egovernment/news/be-nl-governments-will-not                     | Belgium (9873), Netherlands (9903) |
      | /collection/open-source-observatory-osor/event/10th-libre-software-meeting  | France (9884)                      |
