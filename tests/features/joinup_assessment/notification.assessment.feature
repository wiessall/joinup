@api @group-e @demoAssessment
Feature: Notification test for the assessment.

  Scenario: Notifications should be sent whenever a assessment is created.
    Given users:
      | Username            | Roles     | E-mail                           | First name | Family name | Notification frequency |
      | AA Notify moderator | moderator | assessement_moderator@test.com   | AA Notify  | Moderator   | immediate              |
      | AA owner            |           | assessement_owner@test.com       | AA         | Owner       | immediate              |
      | AA member           |           | assessement_membert@test.com     | AA         | Member      | immediate              |
    And the following collection user memberships:
      | collection                         | user           | roles       |
      | Interoperability assessments space | AA owner       | owner       |

    And I am logged in as "AA owner"
    When I am at "/collection/assessments/assessment-reports/questionnaire"
    # 1st page.
    And I fill in "Short title" with "Short title"
    When I select "National public sector body" from "Type of public sector body"
    When I select "Luxembourg" from "Select your country"
    When I fill in "Name of your organisation" with "Pink Floyd"
    And I fill in "Provide the name of the initiative, project or action containing the binding requirements to be assessed:" with "The Wall"
    And I select the radio button "No"
    And I press "Next"
    # 2nd page.
    When I check "Defense"
    And I press "Next"
    # 3rd page.
    When I select the radio button "No"
    And I press "Next"
    # 4th page.
    When I press "Submit"
    Then I should see the success message "Your assessment is being moderated. If approved, a report will be published on the assessment reports page. Moderators might require changes to this submission but you will be notified by email in this case."

    Given I am logged in as "AA member"
    When I go to the homepage of the "Interoperability assessments space" collection
    When I press "Join this collection" in the Header region
    And I check "Notify me when content is published in Interoperability assessments space"
    And I press "Confirm"
    And I should have the following content subscriptions:
      | Interoperability assessments space | assessment_report, discussion, document, event, news, solution, announcements |

    # Ensure the assessment_report option is only available for the assessment
    # collection.
    And I click "My subscriptions"
    Then I should see the text "Assessment report" in the "Interoperability assessments space" card
    But I should not see the text "Assessment report" in the "The Portal" card

    Given I am logged in as "AA Notify moderator"
    And I click the link from email sent to "AA Notify moderator" with subject "Assessment submission (Complete)"
    When I select the radio button "Publish report"
    And I press "Submit"
    And the email sent to "AA member" with subject "Interoperable Europe Portal: Content has been published" contains the following lines of text:
      | AA Notify Moderator has published the new assessment_report - "Assessment report # |
      | You can access the new content at the following link: http                         |
