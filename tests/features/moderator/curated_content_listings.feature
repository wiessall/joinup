@api @group-h
Feature: Curated content listings
  In order to manage the content which is highlighted on the frontpage
  As a moderator
  I should be able to curate content listings

  Scenario: Access the dashboard
    Given I am logged in as a "moderator"
    When I visit "/dashboard"
    And I click "Curated content listings"
    Then I should see the heading "Curated content listings"
    And I should see the following links:
      | In the spotlight    |
    When I click "In the spotlight"
    Then I should see the heading "Update curated content listing In the spotlight"
    And I should see the button "Add item"
    And I should see the button "Save"
    And I should see the button "Reverse"
    And I should see the button "Shuffle"
    And I should see the button "Clear"

  Scenario: Moderators can quickly access curated content listing pages.
    Given I am an anonymous user
    And I am on the homepage
    Then I should not see any contextual links in the "In the spotlight" region

    Given I am logged in as an "authenticated user"
    And I am on the homepage
    Then I should not see any contextual links in the "In the spotlight" region

    Given I am logged in as a "moderator"
    And I am on the homepage
    Then I should see the contextual link "Update curated content" in the "In the spotlight" region

    Given I am on the homepage
    When I click the contextual link "Update curated content" in the "In the spotlight" region
    Then I should see the heading "Update curated content listing In the spotlight"

  @javascript
  Scenario: Regression test for ISAICP-6658
  See https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-6658

    Given collection content:
      | title           |
      | Spacex in space |
    And news content:
      | title          | collection      | topic                                      |
      | Cardio atrophy | Spacex in space | Finance in EU, Supplier exchange, E-health |
    And discussion content:
      | title                | collection      | topic                            |
      | Reference of gravity | Spacex in space | Employment and Support Allowance |
    And document content:
      | title        | collection      | topic                                  |
      | Licrogravity | Spacex in space | HR, Statistics and Analysis, E-justice |
    And event content:
      | title           | collection      | topic                    |
      | Stay at the CSS | Spacex in space | EU and European Policies |
    And the "In the spotlight" content listing contains:
      | type    | label                |
      | content | Cardio atrophy       |
      | content | Reference of gravity |
      | content | Licrogravity         |
      | content | Stay at the CSS      |

    Given I am logged in as a "moderator"
    And I am on the homepage
    And I open the account menu
    Then I click "Dashboard"
    And I click "Curated content listings"
    Then I should see the heading "Curated content listings"

    When I click "In the spotlight"
    Then I press "Shuffle"
    And I press "Save"
    Then I should see the message "The entity subqueue In the spotlight has been updated."
    Then I should see the heading "Curated content listings"

    When I am on the homepage
    And I click the contextual link "Feature" in the "Licrogravity" tile
    Then the "Licrogravity" tile should be marked as featured

    Then I visit the advanced search page
    And the "Licrogravity" tile should be marked as featured
