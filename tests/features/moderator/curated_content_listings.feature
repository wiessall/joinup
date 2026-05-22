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
  Scenario: Tests whether queue can be populated
  Also a regression test for ISAICP-6658

    Given the following "collection" content:
      | title           |
      | Spacex in space |
    And the following "news" content:
      | title          | collection      | topic                                      |
      | Cardio atrophy | Spacex in space | Finance in EU, Supplier exchange, E-health |
    And the following "discussion" content:
      | title                | collection      | topic                            |
      | Reference of gravity | Spacex in space | Employment and Support Allowance |
    And the following "document" content:
      | title        | collection      | topic                                  |
      | Licrogravity | Spacex in space | HR, Statistics and Analysis, E-justice |
    And the following "event" content:
      | title           | collection      | topic                    |
      | Stay at the CSS | Spacex in space | EU and European Policies |

    Given I am logged in as a "moderator"
    And I am on the homepage
    And I open the account menu
    Then I click "Dashboard"
    And I click "Curated content listings"
    Then I should see the heading "Curated content listings"

    When I click "In the spotlight"
    # Populate the queue with the UI.
    And I type "Cardio" in the "items[add_more][new_item][target_id]" autocomplete field
    And I pick "Cardio atrophy" from the "items[add_more][new_item][target_id]" autocomplete suggestions
    And I press "Add item"
    And I press "Save"
    # Check queue min size.
    Then I should see the error message "This queue can not hold less than 4 items."

    Then I type "Reference" in the "items[add_more][new_item][target_id]" autocomplete field
    And I pick "Reference of gravity" from the "items[add_more][new_item][target_id]" autocomplete suggestions
    And I press "Add item"
    Then I type "Licro" in the "items[add_more][new_item][target_id]" autocomplete field
    And I pick "Licrogravity" from the "items[add_more][new_item][target_id]" autocomplete suggestions
    And I press "Add item"
    Then I type "Stay at the CSS" in the "items[add_more][new_item][target_id]" autocomplete field
    And I pick "Stay at the CSS" from the "items[add_more][new_item][target_id]" autocomplete suggestions
    And I press "Add item"
    # Verify that the upper limit is reached.
    Then I should not see the button "Add item"
    And I press "Save"
    Then I should see the message "The entity subqueue In the spotlight has been updated."
    Then I should see the heading "Curated content listings"

    Given I am on the homepage
    Then I should see the following tiles in the "In the spotlight" region:
      | Cardio atrophy       |
      | Reference of gravity |
      | Licrogravity         |
      | Stay at the CSS      |

    # These must be removed if we won't display "featured" status in the
    # new view mode.
    # @todo Revisit and align this in ISAICP-9497.
    And I click the contextual link "Feature" in the "Licrogravity" tile
    Then the "Licrogravity" tile should be marked as featured

    Then I visit the advanced search page
    And the "Licrogravity" tile should be marked as featured
