@api @group-clone
Feature: Test update joinup_core_deploy_0108500.

  Scenario: Navigate to a solution that lacks the EIF perspective, and assert that the link IS NOT present in the left-side menu.
    Given I visit "collection/european-committee-standardization-cen/solution/dublin-core-egovernment-application-profiles"
    Then I should not see the link "EIF view"

  Scenario: Navigate to other solution that has EIF perspective, and assert that the link IS present in the left-side menu.
    Given I visit "collection/justice-law-and-security/solution/leos-open-source-software-editing-legislation"
    Then I should see the link "EIF view"
