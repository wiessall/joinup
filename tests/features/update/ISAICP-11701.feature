@api @group-clone
Feature: Test ISAICP-11701

  @update:joinup_core_deploy_201601
  Scenario: Checks that revisions are fixed
    Given I am logged in as a moderator
    When I go to "/collection/open-source-observatory-osor/interactive-resource-map/revisions"
    Then I should not see "Created by translation job"
    When I go to "/fr/node/704890/revisions"
    Then I should see "Created by translation job"
    When I go to "/es/node/704890/revisions"
    Then I should see "Created by translation job"
    When I go to "/ro/node/704890/revisions"
    Then I should see "Created by translation job"
