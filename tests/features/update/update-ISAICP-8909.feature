@api @group-clone
Feature: Smoke test of deploy hook joinup_core_deploy_110901.

  @update:joinup_core_deploy_110901
  Scenario: Document node '701955' is restored to published.
    Given I go to "/node/701955"
    Then I should see the heading "eTrustEx – Web JavaScript version User Guide v 0.05"
    And the workflow state of the "eTrustEx – Web JavaScript version User Guide v 0.05" content should be "published"
