@api @group-clone @update:joinup_core_deploy_111300
Feature: Ensure that releases without version do not break adding new release

  Scenario: Ensure that releases without version do not break adding new release.
    Given I am logged in as a facilitator of the "LEOS - Open Source software for editing legislation" solution
    And I accept the "Legal notice" agreement
    When I go to the homepage of the "LEOS - Open Source software for editing legislation" solution
    And I click "Add release"
    And I should see the heading "Add Release"
