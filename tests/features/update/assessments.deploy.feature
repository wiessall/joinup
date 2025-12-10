@api @group-clone
Feature: Test assessment update path.

  @update:assessments_deploy_200802
  Scenario:
    # Ensure anonymous can no longer access the reports page.
    When I go to "/collection/assessments/assessment-reports"
    Then I should see the heading "Sign in to continue"

    # Ensure menu links point to the new reports page.
    When I visit "/collection/assessments"
    Then the "Assessments repository" link should point to "/collection/assessments/report/repository"

    # Ensure moderators can still access the reports page.
    When I am logged in as a user with the "moderator" role
    And I go to "/collection/assessments/assessment-reports"
    Then the HTML title of the page should be "Assessments repository"
    And I should see a "#block-views-block-assessment-reports-list" element

    # Ensure the webform is closed.
    When I am logged in as a user with the "authenticated" role
    And I check the box "I agree to the Legal notice document"
    And I press "Submit"
    And I visit "/collection/assessments/assessment-reports/questionnaire"
    Then I should see the message "Sorry… This form is closed to new submissions."
