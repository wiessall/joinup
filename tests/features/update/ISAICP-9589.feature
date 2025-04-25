@api @group-clone
Feature: Tests for ISAICP-9589

  @update:joinup_core_deploy_200400
  Scenario: Test that the latest release redirects properly.
    Given I go to "/collection/european-interoperability-reference-architecture-eira/solution/egovera/release/601-0"
    And I click "Switch to the latest release"
    Then I should be on "/collection/european-interoperability-reference-architecture-eira/solution/egovera-taxation/release/610"
    And I should see the heading "eGovERA for Taxes"
