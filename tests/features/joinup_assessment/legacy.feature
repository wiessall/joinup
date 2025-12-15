@group-clone @api
Feature: Tests the legacy assessments.

  Scenario: Assessment webform access.
    Given I am logged in as a user with the "authenticated" role
    When I visit "/collection/assessments/assessment-reports/questionnaire"
    Then I should see the success message "Sorry… This form is closed to new submissions."
