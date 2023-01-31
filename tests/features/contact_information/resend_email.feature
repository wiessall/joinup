@api @group-e
Feature: Resend validation email from collection/solution about page
    If the email address of a Collection/Solution has not been validated yet,
    then a message must be displayed under the about page

  @terms
  Scenario: Scenario for Collection and Solution
    Given owner:
      | name      | type               |
      | Rotterdam | National authority |
    And the following collection:
      | title           | Rally fanatics           |
      | state           | validated                |
      | description     | Rally fanatics desc      |
      | topic           | Demography, E-inclusion  |
      | owner           | Rotterdam                |
    And the following solution:
      | title               | Klop mirubes                                      |
      | state               | validated                                         |
      | description         | Klop mirubes desc                                 |
      | topic               | Demography, E-inclusion                           |
      | owner               | Rotterdam                                         |
      | solution type       | Non-binding Instrument, Service Discovery Service |
      | spatial coverage    | Italy, Egypt                                      |
      | collection          | Rally fanatics                                    |
    And users:
      | Username         | E-mail                     | First name  | Family name |
      | Ferners Lee      | ferners-lee@example.com    | Ferners     | Lee         |
      | Plot user        | plot.user@example.com      | Plot        | user        |
      | Lope Facilitator | lopfacilitator@example.com | Lope        | Facilitator |
      | Group user       | group.user@example.com     | Group       | User        |
    And the following collection user memberships:
      | collection     | user             | roles       |
      | Rally fanatics | Lope Facilitator | facilitator |
      | Rally fanatics | Lope Facilitator | owner       |
      | Rally fanatics | Plot user        | owner       |
      | Rally fanatics | Group user       | member      |
    And the following solution user memberships:
      | solution     | user               | roles       |
      | Klop mirubes | Plot user          | owner       |
      | Klop mirubes | Lope Facilitator   | facilitator |
      | Klop mirubes | Lope Facilitator   | owner       |
      | Klop mirubes | Group user         | member      |
    # Check scenario for anonymous user
    When I am not logged in
    And I go to the homepage of the "Rally fanatics" collection
    Then I should see the link "About"
    When I click "About"
    Then I should not see the text "Please confirm your contact information to enable users to contact you"

    # Check scenario for Facilitator role
    When I am logged in as a moderator
    And I go to the homepage of the "Rally fanatics" collection
    Then I should see the link "About"
    When I click "About"
    Then I should not see the text "Please confirm your contact information to enable users to contact you"
    When I go to the edit form of the "Rally fanatics" collection
    And I fill in the following:
      | E-mail | confirmation_email.man@example.com     |
      | Name   | Confirmation Invisible Man             |
    And I press "Create contact information"
    And I press "Publish"
    When I go to the homepage of the "Rally fanatics" collection
    And I click "About"
    Then I should see the text "Please confirm your contact information to enable users to contact you"
    Given I am logged in as "Lope Facilitator"
    And I go to the homepage of the "Rally fanatics" collection
    And I click "About"
    Then I should see the text "Please confirm your contact information to enable users to contact you"
    When I click "confirm"
    Then I should see the text "A new confirmation message has been sent to confirmation_email.man@example.com"

    # Check scenario for Member role
    Given I am logged in as "Group user"
    When I go to the homepage of the "Rally fanatics" collection
    And I click "About"
    Then I should not see the text "Please confirm your contact information to enable users to contact you"


    # Scenario for solution
    # Check scenario for Facilitator role
    When I am logged in as a moderator
    And I go to the homepage of the "Klop mirubes" solution
    Then I should see the link "About"
    When I click "About"
    Then I should not see the text "Please confirm your contact information to enable users to contact you"
    When I go to the edit form of the "Klop mirubes" solution
    And I fill in the following:
      | E-mail | confirmation_email2.man@example.com     |
      | Name   | Confirmation Invisible Man 2            |
    And I press "Create contact information"
    And I press "Publish"
    When I go to the homepage of the "Klop mirubes" solution
    And I click "About"
    Then I should see the text "Please confirm your contact information to enable users to contact you"
    Given I am logged in as "Lope Facilitator"
    And I go to the homepage of the "Klop mirubes" solution
    And I click "About"
    Then I should see the text "Please confirm your contact information to enable users to contact you"
    When I click "confirm"
    Then I should see the text "A new confirmation message has been sent to confirmation_email2.man@example.com"

    # Check scenario for Member role
    Given I am logged in as "Group user"
    When I go to the homepage of the "Klop mirubes" solution
    And I click "About"
    Then I should not see the text "Please confirm your contact information to enable users to contact you"


