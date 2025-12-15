@group-clone @api
Feature: Tests the legacy assessments.

  Scenario: Assessment report.
    When I visit "/collection/assessments/assessment-reports/5003"
    Then I should see the heading "Assessment report #5003"

  Scenario: Assessment webform access.
    Given I am logged in as a user with the "authenticated" role
    When I visit "/collection/assessments/assessment-reports/questionnaire"
    Then I should see the success message "Sorry… This form is closed to new submissions."

  Scenario: Webforms can be downloaded in PDF format.
    Given I am logged in as a moderator
    And I visit "/admin/structure/webform/manage/assessment/submission/5003"
    Then I should see the heading "Online form for Assessments: Submission #124"
    And I click "Download PDF"
    And the content type of the response should be "application/pdf"

    When I go to the pdf download link of the latest assessment submission
    Then the content type of the response should be "application/pdf"

