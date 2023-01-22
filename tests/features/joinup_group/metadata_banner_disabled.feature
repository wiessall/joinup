@api @group-d
Feature: Collections/Solutions settings Metadata Banner disabled
  In order to edit collections/solutions settings
  As a collection/solution owner or moderator
  Moderator need to be able to disabled and enabled metadata from banner in collections/solutions

  Scenario: Moderator or collection/solution owner is able to disabled or enabled metadata from banner in collections/solutions
    Given the following collection:
      | title | Rally fanatics |
      | logo  | logo.png       |
      | state | validated      |
    And the following solution:
      | title      | Klop mirubes   |
      | state      | validated      |
      | collection | Rally fanatics |
    And users:
      | Username         | E-mail                     | First name  | Family name |
      | Ferners Lee      | ferners-lee@example.com    | Ferners     | Lee         |
      | Plot user        | plot.user@example.com      | Plot        | user        |
      | Lope Facilitator | lopfacilitator@example.com | Lope        | Facilitator |
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
    Then I should see a featured stats on the header
    And I should see the link "Settings" in the "Entity actions" region
    And I click "Settings" in the "Entity actions" region
    And I should see the text "MetaData Banner disabled"
    Then the "MetaData Banner disabled" checkbox should not be checked
    And I check the box "MetaData Banner disabled"
    And I press "Save"
    And I go to the "Rally fanatics" collection
    Then I should not see a featured stats on the header

    And I click "Settings" in the "Entity actions" region
    And I should see the text "MetaData Banner disabled"
    Then the "MetaData Banner disabled" checkbox should be checked
    And I uncheck the box "MetaData Banner disabled"
    And I press "Save"
    And I go to the "Rally fanatics" collection
    Then I should see a featured stats on the header

    Given I am logged in as a moderator
    When I go to the "Rally fanatics" collection
    And I should see the link "Settings" in the "Entity actions" region

