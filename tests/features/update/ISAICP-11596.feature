@api @group-clone @update:joinup_tmgmt_deploy_201600
Feature: Tmgmt messages

  Scenario Outline: Regression test for https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-11596

    Given I am logged in as a user with the "developer" role
    When I visit "/admin/tmgmt/items/<item>/messages"
    Then I should not see "Source was updated, changes were ignored as job item is still active."

    Examples:
      | item     |
      # Job item
      | 290864   |
      | 290862   |
      | 290875   |
      | 290874   |
      | 290861   |
      | 290882   |
      | 290887   |
      | 290897   |
      | 290876   |
      | 290889   |
      | 290886   |
