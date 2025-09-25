@api @group-f
Feature: Collections/Solutions settings
  In order to edit collections/solutions settings
  As a collection/solution owner or moderator
  Moderator need to be able to edit collections/solutions settings through the UI

  Background:
    Given owner content:
      | name       | owner type |
      | Jonny inc. | Company    |

  Scenario: Edit settings in a collection and solution
    Given collection content:
      | title             |
      | Jillian Land Eels |
    And solution content:
      | title         | collection        |
      | Solution Beta | Jillian Land Eels |
    When I am logged in as a owner of the "Jillian Land Eels" collection
    And I go to the homepage of the "Jillian Land Eels" collection
    And I should not see the link "Settings" in the "Entity actions" region

    When I am logged in as a moderator
    And I go to the homepage of the "Jillian Land Eels" collection
    And I should see the link "Settings" in the "Entity actions" region
    And I click "Settings" in the "Entity actions" region
    Then I should see the heading "Jillian Land Eels collection settings"
    And the "Highlight only the first term appearance" checkbox should be checked
    And the "Announcements enabled" checkbox should be checked

    Then I uncheck "Announcements enabled"
    And I press "Save"
    Then I should see the heading "Jillian Land Eels"

    When I click "Settings" in the "Entity actions" region
    Then the "Highlight only the first term appearance" checkbox should be checked
    And the "Announcements enabled" checkbox should be unchecked

    When I am logged in as a owner of the "Solution Beta" solution
    And I go to the homepage of the "Solution Beta" solution
    And I should see the link "Settings" in the "Entity actions" region
    And I click "Settings" in the "Entity actions" region
    But I should not see the text "Announcements enabled"

    When I am logged in as a moderator
    And I go to the homepage of the "Solution Beta" solution
    And I should see the link "Settings" in the "Entity actions" region
    And I click "Settings" in the "Entity actions" region
    Then I should see the heading "Solution Beta solution settings"
    And the "Announcements enabled" checkbox should be checked

  Scenario: Testing group settings permissions
    Given collection content:
      | title          |
      | Rally fanatics |
    And solution content:
      | title        | collection     |
      | Klop mirubes | Rally fanatics |
    And users:
      | Username         | E-mail                     | First name | Family name |
      | Ferners Lee      | ferners-lee@example.com    | Ferners    | Lee         |
      | Plot user        | plot.user@example.com      | Plot       | user        |
      | Lope Facilitator | lopfacilitator@example.com | Lope       | Facilitator |
    And the following collection user memberships:
      | collection     | user             | roles       |
      | Rally fanatics | Lope Facilitator | facilitator |
      | Rally fanatics | Plot user        | owner       |
    And the following solution user memberships:
      | solution     | user             | roles       |
      | Klop mirubes | Lope Facilitator | owner       |
      | Klop mirubes | Plot user        | facilitator |

    Given I am logged in as "Ferners Lee"
    When I go to the "Rally fanatics" collection
    And I should not see the link "Settings" in the "Entity actions" region
    Then I go to the homepage of the "Klop mirubes" solution
    And I should not see the link "Settings" in the "Entity actions" region

    Given I am logged in as "Plot user"
    When I go to the "Rally fanatics" collection
    And I should see the link "Settings" in the "Entity actions" region
    And I click "Settings" in the "Entity actions" region
    But I should not see the text "Announcements enabled"
    Then I go to the homepage of the "Klop mirubes" solution
    And I should see the link "Settings" in the "Entity actions" region

    Given I am logged in as "Lope Facilitator"
    When I go to the "Rally fanatics" collection
    And I should see the link "Settings" in the "Entity actions" region
    And I click "Settings" in the "Entity actions" region
    But I should not see the text "Announcements enabled"

    Given I am logged in as a moderator
    When I go to the "Rally fanatics" collection
    And I should see the link "Settings" in the "Entity actions" region
    And I click "Settings" in the "Entity actions" region
    And the "Announcements enabled" checkbox should be checked
    Then I go to the homepage of the "Klop mirubes" solution
    And I should see the link "Settings" in the "Entity actions" region
    And I click "Settings" in the "Entity actions" region
    And the "Announcements enabled" checkbox should be checked

  Scenario Outline: Only moderators and owners have access to collection/solution meta entity canonical page
    Given users:
      | Username         | Roles     |
      | John User        |           |
      | John Moderator   | moderator |
      | John Facilitator |           |
      | John Author      |           |
      | John Owner       |           |
      | John Member      |           |
    Given solution content:
      | title           |
      | Custom solution |
    And the following solution user memberships:
      | solution        | user             | roles       |
      | Custom solution | John Owner       | owner       |
      | Custom solution | John Facilitator | facilitator |
      | Custom solution | John Author      | author      |
      | Custom solution | John Member      | member      |

    Given I am logged in as <user>
    When I go to the canonical path of the "solution_settings" meta entity of the "Custom solution" solution_settings "content"
    Then I should get a <code> HTTP response

    Examples:
      | user               | code |
      | "John User"        | 403  |
      | "John Moderator"   | 200  |
      | "John Facilitator" | 200  |
      | "John Author"      | 403  |
      | "John Owner"       | 200  |
      | "John Member"      | 403  |
