@api @group-h
Feature:
  As a moderator
  I should be able to create and edit webforms and manage submissions

  Scenario: Moderators can create and edit webforms
    Given I am logged in as a "moderator"
    And I am on the homepage
    And I click "Dashboard" in the "Navigation bar" region
    And I click "Manage webforms" in the "Content" region
    And I click "Add webform"
    And I fill in "Title" with "Test webform"
    And I fill in "Machine-readable name" with "test_webform"
    And I press "Save"
    And I should see the success message "Webform Test webform created"
    And I should see the following links:
      | View        |
      | Test        |
      | Results     |
      | Build       |
      | Settings    |
      | Add element |
      | Add page    |
      | Add layout  |
    And I click "Add element"
    And I click "Checkbox"
    And I fill in the following:
      | Key   | test_checkbox |
      | Title | Test checkbox |
    And I press "Save"
    And I should see the success message "Test checkbox has been created"
    And I visit "/form/test-webform"
    And I should see the heading "Test webform"
    And I check the box "test_checkbox"
    And I press "Submit"
    And I should see the text "New submission added to Test webform."
    # Cleanup the webform programmatically.
    And I delete the "test_webform" webform
