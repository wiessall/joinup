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
      | Highlighted event   |
      | Highlighted content |
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
    And I should not see any contextual links in the "Highlighted event" region
    And I should not see any contextual links in the "Highlighted content" region

    Given I am logged in as an "authenticated user"
    And I am on the homepage
    Then I should not see any contextual links in the "In the spotlight" region
    And I should not see any contextual links in the "Highlighted event" region
    And I should not see any contextual links in the "Highlighted content" region

    Given I am logged in as a "moderator"
    And I am on the homepage
    Then I should see the contextual link "Update curated content" in the "In the spotlight" region
    And I should see the contextual link "Update curated content" in the "Highlighted event" region
    And I should see the contextual link "Update curated content" in the "Highlighted content" region

    Given I am on the homepage
    When I click the contextual link "Update curated content" in the "In the spotlight" region
    Then I should see the heading "Update curated content listing In the spotlight"

    Given I am on the homepage
    When I click the contextual link "Update curated content" in the "Highlighted event" region
    Then I should see the heading "Update curated content listing Highlighted event"

    Given I am on the homepage
    When I click the contextual link "Update curated content" in the "Highlighted content" region
    Then I should see the heading "Update curated content listing Highlighted content"

  @javascript
  Scenario: Regression test for ISAICP-6658
  See https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-6658

    Given collection:
      | title | Spacex in space |
      | state | published       |
    And news content:
      | title          | collection    | topic                                      | state     |
      | Cardio atrophy | Spacex in space | Finance in EU, Supplier exchange, E-health | published |
    And discussion content:
      | title                | collection      | topic                            | state     |
      | Reference of gravity | Spacex in space | Employment and Support Allowance | published |
    And document content:
      | title        | collection      | topic                                  | state     |
      | Licrogravity | Spacex in space | HR, Statistics and Analysis, E-justice | published |
    And event content:
      | title           | collection      | topic                    | state     |
      | Stay at the CSS | Spacex in space | EU and European Policies | published |
    And the "Highlighted event" content listing contains:
      | type    | label           |
      | content | Stay at the CSS |
    And the "Highlighted content" content listing contains:
      | type     | label           |
      | document | Licrogravity    |
    And the "In the spotlight" content listing contains:
      | type    | label                |
      | content | Cardio atrophy       |
      | content | Reference of gravity |
      | content | Licrogravity         |

    Given I am logged in as a "moderator"
    And I am on the homepage
    And I open the account menu
    Then I click "Dashboard"
    And I click "Curated content listings"
    Then I should see the heading "Curated content listings"
    And I should see the following links:
      | Highlighted event   |
      | Highlighted content |
      | In the spotlight    |

    When I click "Highlighted event"
    And I press "Save"
    Then I should see the heading "Curated content listings"

    When I click "Highlighted content"
    And I press "Save"
    Then I should see the heading "Curated content listings"

    When I click "In the spotlight"
    Then I press "Shuffle"
    And I press "Save"
    Then I should see the message "The entity subqueue In the spotlight has been updated."
    Then I should see the heading "Curated content listings"

    When I am on the homepage
    And I click the contextual link "Feature" in the "Licrogravity" tile
    Then the "Licrogravity" tile should be marked as featured

    Then I visit the search page
    And the "Licrogravity" tile should be marked as featured

  @loggedErrors @javascript
  Scenario: Regression test for ISAICP-8152
  See https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-8152

    Given collection:
      | title | Spacex in space |
      | state | published       |
    And news content:
      | title          | collection      | topic                                      | state     |
      | Cardio atrophy | Spacex in space | Finance in EU, Supplier exchange, E-health | published |

    Given I am logged in as a "moderator"
    When there are no preexisting log records on the site
    And I visit "/admin/structure/entityqueue/highlighted_content/highlighted_content"
    And I fill in "edit-items-add-more-new-item-target-id" with "Cardio atrophy"
    And I press "Add item"
    And I press "Save"

    Then I should see the message "The entity subqueue Highlighted content has been updated."
    And the site should not have log records with level lower than "notice"
