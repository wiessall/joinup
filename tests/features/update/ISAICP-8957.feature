@api @group-clone
Feature: Test updates of ISAICP-8957.

  @update:joinup_core_deploy_200501
  Scenario: Test that results did not change in Search API Field paragraphs.
    Given I go to "/collection/open-source-observatory-osor/case-studies"
    Then I should see the text "129 Document"

    Given I go to "/collection/portal/how-articles"
    Then I should see the heading "How to get started with the Interoperable Europe Portal"
    And I should see the following headings:
      | How to get started on the Portal: The Homepage                           |
      | How to find and discover information on the Portal                       |
      | How to create same-page links using anchor tags                          |
      | How to get help from the Interoperable Europe Support Centre             |
      | How to contact other users on the Portal via the Private messaging tool? |
