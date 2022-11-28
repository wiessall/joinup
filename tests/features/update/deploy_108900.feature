@api @group-clone
Feature: Covers \joinup_core_deploy_108900().

  Scenario: Test that news creation works.

    Given I am logged in as a moderator
    And I am on the homepage
    When I click "Interoperable Europe"
    And I click "News"
    Then the url should match "/interoperable-europe/latest-news"

    When I click "Add news" in the plus button menu
    Then the response status code should be 200
    And the url should match "/interoperable-europe/news/add"
    And I should see the heading "Add news"
