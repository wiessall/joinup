@api @group-h
Feature: Site alerts
  In order to announce scheduled downtime
  As a moderator
  I want to be able to display a site wide alert

  @javascript
  Scenario: Show a site wide alert and hide it again
    # Create a site wide alert as a moderator.
    Given I am logged in as a moderator
    When I click "Site alerts" in the "Administration toolbar" region
    Then I should see the text "There are no Sitewide Alerts yet."

    When I click "Add new Sitewide Alert"
    And I fill in "Name" with "Site maintenance 18:00-20:00"
    And I select "Medium" from "Alert Style"
    And I select "Other" from "Category"
    And I enter "Between 18:00 and 20:00 the site will be temporarily unavailable due to scheduled maintenance" in the "Alert Message" wysiwyg editor
    And I press "Save"

    # Anonymous users should see the alert.
    Given I am not logged in
    And I am on the homepage
    Then I wait until the page contains the text "Between 18:00 and 20:00 the site will be temporarily unavailable due to scheduled maintenance"
    Then I click "advanced search"
    Then I should be on the advanced search page
    And I wait until the page contains the text "Between 18:00 and 20:00 the site will be temporarily unavailable due to scheduled maintenance"

    # Deactivate the alert without deleting it, so it can be used again later.
    Given I am logged in as a moderator
    When I click "Site alerts" in the "Administration toolbar" region
    And I click "Edit"
    And I uncheck "Active"
    And I press "Save"

    # The alert should no longer be shown.
    Given I am not logged in
    And I am on the homepage
    Then I should not see the text "Between 18:00 and 20:00 the site will be temporarily unavailable due to scheduled maintenance"

    # Re-enable the alert, it should appear again.
    Given I am logged in as a moderator
    When I click "Site alerts" in the "Administration toolbar" region
    And I click "Edit"
    And I check "Active"
    And I press "Save"

    Given I am not logged in
    And I am on the homepage
    And I wait until the page contains the text "Between 18:00 and 20:00 the site will be temporarily unavailable due to scheduled maintenance"
    And I go to "/interoperable-europe"
    And I wait until the page contains the text "Between 18:00 and 20:00 the site will be temporarily unavailable due to scheduled maintenance"

    # An alert can also be removed entirely, if it does not need to be reused.
    Given I am logged in as a moderator
    When I click "Site alerts" in the "Administration toolbar" region
    And I click "Edit"

    When I click "edit-delete"
    And a modal should open
    Then I press "Delete" in the "Modal buttons" region
    And the modal should be closed

    Then I should see the message "The Sitewide Alert Site maintenance 18:00-20:00 has been deleted."

    Given I am not logged in
    And I am on the homepage
    Then I should not see the text "Between 18:00 and 20:00 the site will be temporarily unavailable due to scheduled maintenance"
    And I go to "/interoperable-europe"
    Then I should not see the text "Between 18:00 and 20:00 the site will be temporarily unavailable due to scheduled maintenance"
