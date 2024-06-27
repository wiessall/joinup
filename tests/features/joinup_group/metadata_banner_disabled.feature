@api @group-d
Feature: Collections/Solutions settings Metadata Banner disabled
  In order to edit collections/solutions settings
  As a collection/solution owner or moderator
  Moderator need to be able to disabled and enabled metadata from banner in collections/solutions

  Scenario: Moderator or collection/solution owner is able to disable or enable metadata from banner in collections/solutions
    Given collections:
      | title          | state     |
      | Rally fanatics | published |
      | Legion of Doom | published |
    And the following solution:
      | title      | Klop mirubes   |
      | state      | published      |
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
      | Legion of Doom | Plot user        | owner       |
    And the following solution user memberships:
      | solution     | user               | roles       |
      | Klop mirubes | Plot user          | owner       |
    And "news" content:
      | title                         | headline                                    | body                                                                    | state            | author        | collection     |
      | Creating Justice League       | 6 Members to start with                     | TBD                                                                     | published        | Eagle         | Rally fanatics |
    And "news" content:
     | title                         | headline                                    | body                                                                    | state            | author        | solution       |
     | Hawkgirl is a spy             | Her race lies in another part of the galaxy | Hawkgirl has been giving information about Earth to Thanagarians.       | published        | Eagle         | Klop mirubes   |

    Given I am logged in as "Ferners Lee"
    When I go to the "Rally fanatics" collection
    And I should not see the link "Settings" in the "Entity actions" region
    Then I go to the homepage of the "Klop mirubes" solution
    And I should not see the link "Settings" in the "Entity actions" region

    #Tests for collection
    Given I am logged in as "Plot user"
    When I go to the "Rally fanatics" collection
    Then I should see a featured stats on the header
    And I should see the link "Settings" in the "Entity actions" region
    When I click "Settings" in the "Entity actions" region
    Then I should see the text "Metadata banner disabled"
    And the "Metadata banner disabled" checkbox should not be checked
    When I check the box "Metadata banner disabled"
    And I press "Save"
    And I go to the "Rally fanatics" collection
    Then I should not see a featured stats on the header
    When I go to the "Creating Justice League" news
    Then I should not see a featured stats on the header

    When I go to the "Rally fanatics" collection
    And I click "Settings" in the "Entity actions" region
    Then I should see the text "Metadata banner disabled"
    And the "Metadata banner disabled" checkbox should be checked
    When I uncheck the box "Metadata banner disabled"
    And I press "Save"
    And I go to the "Rally fanatics" collection
    Then I should see a featured stats on the header
    When I go to the "Creating Justice League" news
    Then I should see a featured stats on the header

    When I go to the "Legion of Doom" collection
    Then I should see a featured stats on the header
    And I click "Settings" in the "Entity actions" region
    And I should see the text "Metadata banner disabled"
    Then the "Metadata banner disabled" checkbox should not be checked
    And I check the box "Metadata banner disabled"
    And I press "Save"
    And I go to the "Legion of Doom" collection
    Then I should not see a featured stats on the header

    Given I am logged in as a moderator
    When I go to the "Rally fanatics" collection
    And I should see the link "Settings" in the "Entity actions" region

    #Tests for solution
    Given I am logged in as "Plot user"
    When I go to the "Klop mirubes" solution
    Then I should see a featured stats on the header
    And I should see the link "Settings" in the "Entity actions" region
    When I click "Settings" in the "Entity actions" region
    Then I should see the text "Metadata banner disabled"
    And the "Metadata banner disabled" checkbox should not be checked
    When I check the box "Metadata banner disabled"
    And I press "Save"
    And I go to the "Klop mirubes" solution
    Then I should not see a featured stats on the header
    When I go to the "Hawkgirl is a spy" news
    Then I should not see a featured stats on the header
