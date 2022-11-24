@api @group-d
Feature:
  As a moderator
  When I go to the overview page of topic meta entity type
  I want to be able to see Topic Meta Entity page.
  I want to be able to synchronize content between topic meta entity and topic taxonomy term.
  @terms
  Scenario: Show list of topics meta entity type.
    When I am logged in as a moderator
    And I go to "/dashboard"
    Then I should see the link Topics
    When I click Topics
    Then I should see the heading "Topic Meta Entity"
  @terms
  Scenario: Synchronization part from topic taxonomy term to topic meta entity type.
    When I am logged in as a moderator
    And I go to "/dashboard"
    Then I should see the link Topics
    When I click Topics
    Then I should see the heading "Topic Meta Entity"
    And I click "Edit" in the "E-justice" row
    And I fill in "Title" with "Topic2"
    And I press "edit-submit"
    Then I should see the text "Topic2"
    When I go to "/admin/structure/taxonomy/manage/topic/overview"
    Then I should see the text "Topic2"
