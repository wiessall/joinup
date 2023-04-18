@group-clone @api
Feature: Ampersand character is not decoded properly.

  Scenario: Visit /topic/digital-government/digital-ready-policymaking and check if ampersand character is decoded properly.
    Given I am an anonymous user
    When I visit "/topic/digital-government/digital-ready-policymaking"
    Then I should see the text "What do we mean by Legislation & Technology?" in the "Legislation & Technology" tile
    And I should not see the text "What do we mean by Legislation &amp; Technology?" in the "Legislation & Technology" tile
