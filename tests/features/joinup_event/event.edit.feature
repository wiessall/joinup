@api @group-e
Feature: "Event page" editing.
  In order to manage events
  As an owner of the event
  I need to be able to edit it.

  Scenario: Add and remove map
    Given the following "collection" content:
      | title  | logo     |
      | Heroes | logo.png |
    And I am logged in as a facilitator of the "Heroes" collection
    When I go to the homepage of the "Heroes" collection
    And I click "Add event" in the plus button menu
    When I fill in the following:
      | Title             | Best event                                               |
      | Abstract          | It will be the best event this year.                     |
      | Physical location | Tower Bridge, Tower Bridge Road, London, United Kingdom. |

    And I select "EU and European Policies" options from "Topic"
    And I press "Save as draft"
    Then I should see the heading "Best event"
    And I should see a map on the page
    When I click "Edit" in the "Entity actions" region
    Then I should see the heading "Edit Event Best event"
    When I clear the field "Physical location"
    And I enter the following for the "Virtual location" link field:
      | URL                              | Title           |
      | https://share-and-reuse.example/ | Share and reuse |
    And I press "Save as draft"
    Then I should see the heading "Best event"
    And I should not see a map on the page

  Scenario Outline: Owners and moderators should be able to view the Edit link.
    Given users:
      | Username |
      | <user>   |
    And the following "collection" content:
      | title        | description        | moderation |
      | <collection> | Sample description | yes        |
    And the following collection user memberships:
      | collection   | user   | roles  |
      | <collection> | <user> | <role> |
    And "event" content:
      | title   | author | collection   |
      | <event> | <user> | <collection> |
    When I am logged in as "<user>"
    And I go to the "<event>" event
    Then I should see the link "Edit" in the "Entity actions" region
    # A moderator should always be able to edit the content.
    When I am logged in as a moderator
    And I go to the "<event>" event
    Then I should see the link "Edit" in the "Entity actions" region
    Examples:
      | user           | collection        | event                    | role        |
      | Kristina Silva | Gate of Flames    | Gate of Flames           |             |
      | Irvin West     | Bare Past         | Name of Consort          | member      |
      | Emilio Garcia  | The Final Bridges | The Dreaming of the Game | facilitator |

  Scenario: A solution facilitator can edit his content.
    Given users:
      | Username       |
      | Krista Garrett |
    Given the following "solution" content:
      | title                | description        | moderation |
      | Dreamer in the Snake | Sample description | no         |
    And the following solution user memberships:
      | solution             | user           | roles       |
      | Dreamer in the Snake | Krista Garrett | facilitator |
    And "event" content:
      | title       | author         | solution             | state    |
      | Silver Snow | Krista Garrett | Dreamer in the Snake | proposed |
    When I am logged in as "Krista Garrett"
    And I go to the "Silver Snow" event
    Then I should see the link "Edit" in the "Entity actions" region
    # A moderator should always be able to edit the content.
    When I am logged in as a moderator
    And I go to the "Silver Snow" event
    Then I should see the link "Edit" in the "Entity actions" region
