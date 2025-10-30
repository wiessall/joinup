@api
Feature: Dashboard
  In order to see an overview of my related information at a glance
  As an authenticated user
  I should have access to a dashboard

  Scenario: Access the dashboard
    Given I am logged in as a "moderator"
    When I am on the homepage
    Then I should see the link "Dashboard"
    When I click "Dashboard"
    Then I should see the heading "Dashboard"
    And I should see the following links:
      | My subscriptions         |
      | Licences overview        |
      | Curated content listings |
      | Topics                   |
      | Files                    |
      | Manage webforms          |
      | OAuth2 Client Requests   |
    And I should not see the button "Submit"

  Scenario Outline: Moderators should see proposed collections and solutions.
    Given I am logged in as a "moderator"
    And <group type> content:
      | title           | state     |
      | Some berry pie  | published |
      | Some cherry pie | proposed  |
    When I go to my dashboard
    Then I should not see the "Some berry pie" tile
    But I should see the "Some cherry pie" tile

    Examples:
      | group type |
      | collection |
      | solution   |
