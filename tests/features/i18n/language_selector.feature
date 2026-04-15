@api @group-g @javascript
Feature: Language selector block and popover functionality
  In order to switch between different languages
  As any user
  I want to be able to see and use the language selector

  Scenario Outline: All users can see and use the language selector
    Given I am <login_state>

    When I am on the homepage
    Then I should see "EN" in the "Top Bar" region

    When I press "EN" in the "Top Bar" region
    And I should see "FR" in the "Language picker" region
    And I should see "DE" in the "Language picker" region
    And I should see "ES" in the "Language picker" region

    When I click "French" in the "Language picker" region
    Then I should be on "/fr"
    And I should see "FR" in the "Top Bar" region

    Examples:
      | login_state                                            |
      | not logged in                                          |
      | logged in as a user with the "authenticated" role      |
