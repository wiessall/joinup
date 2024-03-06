@api @group-d
Feature:
  As a moderator
  I should be able to create and edit webforms and manage submissions

  Scenario Outline: Only privileged users can access the webform management page.
    Given <user_condition>
    And I go to "/admin/structure/webform"
    And I go to "admin/structure/webform/submissions/manage"
    Then <expectation>

    Examples:
      | user_condition                            | expectation                                    |
      | I am an anonymous user                    | I should see the heading "Sign in to continue" |
      | I am logged in as an "authenticated user" | the response status code should be 403         |
      | I am logged in as a moderator             | the response status code should be 200         |

  Scenario: Moderators can create and edit webforms
    Given I am logged in as a "moderator"
    When I visit "/admin/structure/webform/add"
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
    And I visit "admin/structure/webform/manage/test_webform/element/add/checkbox"
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
