@api @group-d
Feature:
  As a moderator
  When I go to the overview page of topic meta entity type
  I want to be able to see Topic Meta Entity page.
  @terms
  Scenario: Show list of topics meta entity type.
    When I am logged in as a moderator
    And I go to "/dashboard"
    Then I should see the link Topics
    When I click Topics
    Then I should see the heading "Topics"
