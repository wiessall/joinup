@api @group-g
Feature: Language selector block and popover functionality
  In order to switch between different languages
  As a user with appropriate permissions
  I want to be able to see and use the language selector

  Background:
    Given users:
      | Username        | Roles      | E-mail                   |
      | Regular User    |            | regular.user@example.com |
      | Translator User | Translator | translator@example.com   |

  Scenario Outline: Users without translator role cannot see top bar region
    Given I am <login_state>

    When I am on the homepage
    Then I should not see the "Top Bar" region

    Examples:
      | login_state                 |
      | not logged in               |
      | logged in as "Regular User" |

  @javascript
  Scenario: Language selector popover opens when clicked
    Given I am logged in as "Translator User"

    When I am on the homepage
    Then I should see "EN" in the "Top Bar" region

    When I press "EN" in the "Top Bar" region
    Then a popover should open
    And I should see "FR" in the "Popover" region
    And I should see "DE" in the "Popover" region
    And I should see "ES" in the "Popover" region

    When I click "FR" in the "Popover" region
    Then I should be on "/fr"
    And I should see "FR" in the "Top Bar" region
