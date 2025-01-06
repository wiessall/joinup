@api @group-clone
Feature: Webforms

  # @covers \joinup_webform_webform_access_rules_alter()
  Scenario: Moderators cannot administer a webform delivered by config sync but
    they are able to create forms via UI.

    Given I am logged in as a moderator
    When I go to "/admin/structure/webform/assessment"
    Then the response status code should be 404

    # But they are able to manage webforms created via UI.
    When I go to "/admin/structure/webform"
    And I click "Add webform"
    When I fill in "Title" with "Hello World!"
    And I fill in "Machine-readable name" with "hello_world"
    And I press "Save"
    Then I should see "Please add elements to this webform."

    When I go to "/admin/structure/webform"
    Then I should see the link "Hello World!"
    When I click the "Build" link in the "Hello World!" row
    Then I should see the link "Add element"
    When I go to "/admin/structure/webform"
    And I click the "Delete" link in the "Hello World!" row
    And I check "Yes, I want to delete the Hello World! webform"
    And I press "Delete"
    Then I should see the success message "The webform Hello World! has been deleted."
