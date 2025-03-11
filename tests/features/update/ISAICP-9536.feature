@api @group-clone
Feature: Test orphaned content

  @update:joinup_core_deploy_200305
  Scenario: Assert that no orphaned content exist in the website.
    Then no orphaned content should exist in the website
