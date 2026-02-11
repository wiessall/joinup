@api @group-clone
Feature: Test ISAICP-10575.

  @update:assessments_deploy_200803
  Scenario: "European Union" term is imported
    When I visit "/taxonomy/term/10120"
    Then I should see the heading "European Union"
