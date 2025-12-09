@api @group-clone
Feature: Test ISAICP-10575.

  @update:joinup_taxonomy_deploy_200800
  Scenario: "European Union" term is imported
    When I visit "/taxonomy/term/10120"
    Then I should see the heading "European Union"
