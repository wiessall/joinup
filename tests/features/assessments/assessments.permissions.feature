@api @group-a
Feature: Assessment report permissions and access control
  Test that different user roles have appropriate access to assessment operations

  Background:
    Given public_organisation assessments:
      | name              | organisation_type          |
      | Test Organization | european_union_institution |

  Scenario Outline: Logged-in users with different permissions must access draft creation
    Given I am logged in as a "<user>"
    When I go to "/collection/assessments/report/create"
    Then I should see the button "Save as draft"
    And I <submit button state> the button "Submit"
    And I <publish button state> the button "Publish"
    Examples:
      | user                | submit button state | publish button state |
      | authenticated user  | should see          | should not see       |
      | assessment_reporter | should not see      | should see           |
      | moderator           | should not see      | should see           |

  Scenario: Users with the "view assessment listing" permissions can see assessment type in assessment listing
    Given I am logged in as a user with the "view assessment listing" permissions
    When I go to "/admin/content/assessment"
    Then I should see "Public organisation"

  Scenario: Moderators see correct action buttons on published assessment edit form
    Given report assessments:
      | name          | state     |
      | Review Report | published |

    When I am logged in as a "moderator"
    When I go to the edit form of the "Review Report" assessment
    And I should see the button "Update"
    And I should see the link "Delete"
    And I should not see the button "Submit"

  @javascript
  Scenario: Published assessments are viewable by anonymous and authenticated users
    Given user:
      | Username    | pol2025              |
      | First name  | Pol                  |
      | Family name | Gomez                |
      | E-mail      | pol.gomez@example.pt |

    And I am logged in as pol2025
    And I go to "/collection/assessments/report/create"

    When I fill in "Short title" with "Test permissions in Assessment Report 2025"
    And I select "European Union institution" from "Type of public sector body"
    And I wait for AJAX to finish
    Then I should not see the button "…or add new"
    When I select "Test Organization" from "Select organisation"

    # Binding requirements
    When I click "New binding requirement"
    And a modal should open

    When I click "Initiative" in the "Modal content" region
    And I fill in "expression[0][inline_entity_form][initiative_name][0][value]" with "Digital Transformation Initiative" in the "Modal content" region
    And I fill in "expression[0][inline_entity_form][description][0][value]" with "Some description" in the "Modal content" region

    When I click "Requirements" in the "Modal content" region
    And I fill in "description[0][value]" with "This is the general binding requirement description" in the "Modal content" region

    When I click "Concerned public services" in the "Modal content" region
    And I fill in "affects[0][inline_entity_form][name][0][value]" with "eIDAS Authentication Service" in the "Modal content" region

    Then I press "Save" in the "Modal buttons" region
    Then I press "Submit"
    Then I should see the success message containing "New assessment Test permissions in Assessment Report 2025 has been created."
    And I should see the heading "Test permissions in Assessment Report 2025"

    # Check draft state.
    Given I am an anonymous user
    Then I go to the "Test permissions in Assessment Report 2025" report
    And I should not see the heading "Test permissions in Assessment Report 2025"

    Given I am logged in as an "authenticated user"
    Then I go to the "Test permissions in Assessment Report 2025" report
    And I should see the heading "Access denied"

    Given I am logged in as a "moderator"
    Then I go to the "Test permissions in Assessment Report 2025" report
    And I should see the heading "Test permissions in Assessment Report 2025"

    Given I am logged in as a "assessment_moderator"
    When I go to the moderate form of the "Test permissions in Assessment Report 2025" assessment report
    And I press "Publish"
    Then I should see the success message containing "The assessment report has been published."

    # Check published state.
    Given I am an anonymous user
    Then I go to the "Test permissions in Assessment Report 2025" report
    And I should see the heading "Test permissions in Assessment Report 2025"

    Given I am logged in as an "authenticated user"
    Then I go to the "Test permissions in Assessment Report 2025" report
    And I should see the heading "Test permissions in Assessment Report 2025"

    Given I am logged in as a "assessment_reporter"
    Then I go to the "Test permissions in Assessment Report 2025" report
    And I should see the heading "Test permissions in Assessment Report 2025"

    # Verify permissions after assigning the new role.
    Given I am logged in as a "moderator"
    And I click "People" in the "Administration toolbar" region
    And I click "Edit" in the "Pol Gomez" row
    And I check the box "Assessment reporter"
    And I press "Save"
    Then I should see the message "The user has been notified that their account has been updated."

    And I am logged in as pol2025
    And I go to "/collection/assessments/report/create"
    Then I should see the button "Publish"

  Scenario: Assessment add page shows only accessible bundles for authenticated users
    Given I am logged in as an "authenticated user"
    When I go to "/collection/assessments/add"
    Then I should see the link "Assessment report"
    And I should not see the link "Expression"
    And I should not see the link "Participation"
    And I should not see the link "Public organisation"
    And I should not see the link "Public service"
