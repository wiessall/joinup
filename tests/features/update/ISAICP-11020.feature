@api @group-clone
Feature: Test duplicated files.

  @update:joinup_core_deploy_201100
  Scenario: Check duplicated files in the report
    Given I am logged in as a moderator
    When I go to "/collection/assessments/report/359"
    Then all "Supporting documents" file links should be different

    When I go to "/collection/assessments/report/464"
    Then all "Supporting documents" file links should be different
