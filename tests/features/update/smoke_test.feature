@group-clone @api
Feature: Minimal install clone testing.

  Scenario: Visit Joinup collection.

    Given I am an anonymous user
    When I visit "/collection/joinup"
    Then I should see the link "Federation of solutions"
