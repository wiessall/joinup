@group-clone @api
Feature: Test joinup_core_deploy_108401.

  Scenario: Ensure announcements are enable in existing collection.
    When I am logged in as a moderator
    And I go to the homepage of the "EC Open Source Programme Office" collection
    And I should see the link "Settings" in the "Entity actions" region
    And I click "Settings" in the "Entity actions" region
    Then I should see the heading "EC Open Source Programme Office collection settings"
    And the "Announcements enabled" checkbox should be checked

    Then I go to the homepage of the "Joinup" collection
    And I should see the link "Settings" in the "Entity actions" region
    And I click "Settings" in the "Entity actions" region
    Then I should see the heading "Joinup collection settings"
    And the "Announcements enabled" checkbox should be checked
