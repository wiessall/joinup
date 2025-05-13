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

  Scenario Outline: Moderators should see proposed collections and solutions.
    Given I am logged in as a "moderator"
    And <group type> content:
      | title          | state     |
      | Some berry pie | published |
    When I go to my dashboard
    Then I should not see the "Some berry pie" tile
    When I go to the edit form of the "Some berry pie" <group type>
    And I fill in "Motivation" with "I want to know."
    And I press "Propose"
    Then I should see the success message "<Group type> Some berry pie has been updated."
    When I go to my dashboard
    Then I should see the "Some berry pie" tile
    When I go to the edit form of the "Some berry pie" <group type>
    And I press "Publish"
    When I go to my dashboard
    Then I should not see the "Some berry pie" tile

    Examples:
      | group type | Group type |
      | collection | Collection |
      # Fix this in https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-9633
      #| solution   | Solution   |
