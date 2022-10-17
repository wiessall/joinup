@api @group-clone
Feature: Test update deploy_0108601.

  Scenario: Navigate to a joinup-licensing-assistant solution, and assert that the link IS NOT present in the left-side menu for not anonymous user.
    Given I am an anonymous user
    When I visit "/collection/eupl/solution/joinup-licensing-assistant/jla-compatibility-checker"
    Then I should not see the link "Compatibility Documents"

  Scenario: Navigate to a joinup-licensing-assistant solution, and assert that the link IS present in the left-side menu for Licence manager role.
    Given I am logged in as a user with the "Licence manager" role
    When I visit "/collection/eupl/solution/joinup-licensing-assistant/jla-compatibility-checker"
    Then I should see the heading "Legal notice"
    When I check "agree"
    And I press "Submit"
    Then I should see the heading "JLA - Compatibility Checker"
    And I should see the link "Compatibility Documents"
