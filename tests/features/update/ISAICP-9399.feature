@api @group-clone
Feature: Check 'Events calendar' was created.

  @update:joinup_core_deploy_200201
  Scenario: Check 'Events calendar' was created.
    Given I visit "/upcoming-events"
    Then I should see the heading "Events calendar"
