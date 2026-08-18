@api @group-clone @update:joinup_core_deploy_201600
Feature: Archive obsolete content

  Scenario Outline: Regression test for https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-11683

    Given I am logged in as a user with the "administrator" role
    When I go to "/node/<nid>"
    Then I should see the text "Archived" in the "Header"

    Examples:
      | nid    |
      | 732594 |
      | 725778 |
      | 724923 |
      | 724794 |
      | 722254 |
      | 734892 |
      | 726278 |
      | 732118 |
      | 732120 |
      | 724932 |
      | 733290 |
      | 733246 |
      | 724792 |
      | 724920 |
      | 723168 |
      | 725794 |
      | 723283 |
      | 733612 |
      | 726219 |
      | 725487 |
      | 733247 |
      | 733291 |
      | 733334 |
      | 733094 |
      | 733084 |
      | 733162 |
      | 733161 |
      | 724971 |
      | 724970 |
      | 724906 |
      | 725525 |
