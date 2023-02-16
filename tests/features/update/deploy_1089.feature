@api @group-clone
Feature: Covers Release 1.89.

  Scenario: Test that news creation works (108900).

    Given I am logged in as a moderator
    And I am on the homepage
    When I click "Interoperable Europe"
    And I click "News"
    Then the url should match "/interoperable-europe/latest-news"

    When I click "Add news" in the plus button menu
    Then the response status code should be 200
    And the url should match "/interoperable-europe/news/add"
    And I should see the heading "Add news"

  Scenario: Test that spam files are deleted (108901).
    Given I go to "/sites/default/files/document/2022-12/free-cash-app-legitG2.pdf"
    Then the response status code should be 404

  Scenario: Smoke test for the replacement of field_content_listing (108900).
    Given I go to "/interoperable-europe"
    Then I should see results in the "news" carousel

    Given I go to "/collection/joinup"
    Then I should see 12 tiles
