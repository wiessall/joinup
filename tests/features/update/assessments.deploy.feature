@api @group-clone
Feature: Test assessment update path.

  @update:assessments_deploy_200802
  Scenario:
    When I visit "/collection/assessments/assessments-repository"
    Then I should see the heading "Sign in to continue"
    When I visit "/collection/assessments"
    Then the "Assessments repository" link should point to "/collection/assessments/report/repository"
