@api @group-clone
Feature: Test assessments epic.

  @update:joinup_assessment_deploy_200100
  Scenario: Check assessment report page update
    Given I am logged in as a moderator
    When I visit "/collection/assessments/assessment-reports"
    Then I should see the heading "Assessment reports"
    And I should see the link "New assessment"
    And I should see "There's are no published assessment reports yet. You can add one by filling this questionnaire"
