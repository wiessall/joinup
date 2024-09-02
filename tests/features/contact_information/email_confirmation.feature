@api @group-c
Feature: Resend validation email from collection/solution about page
  If the email address of a Collection/Solution has not been published yet,
  then a message must be displayed under the about page

  Scenario: Scenario for Collection and Solution
    Given owner content:
      | name      | owner type |
      | Rotterdam | Company    |
    And collection content:
      | title          | state     | description         | topic                   | owner     |
      | Rally fanatics | published | Rally fanatics desc | Demography, E-inclusion | Rotterdam |
    And solution content:
      | title        | state     | abstract             | description       | topic                   | owner     | solution type                           | spatial coverage | collection     |
      | Klop mirubes | published | Klop mirubes abstact | Klop mirubes desc | Demography, E-inclusion | Rotterdam | Non-binding Instrument, Logging Service | Belgium, Greece  | Rally fanatics |
    And users:
      | Username         | E-mail                     | First name | Family name |
      | Ferners Lee      | ferners-lee@example.com    | Ferners    | Lee         |
      | Plot user        | plot.user@example.com      | Plot       | user        |
      | Lope Facilitator | lopfacilitator@example.com | Lope       | Facilitator |
      | Group user       | group.user@example.com     | Group      | User        |
    And the following collection user memberships:
      | collection     | user             | roles       |
      | Rally fanatics | Lope Facilitator | facilitator |
      | Rally fanatics | Lope Facilitator | owner       |
      | Rally fanatics | Plot user        | owner       |
      | Rally fanatics | Group user       | member      |
    And the following solution user memberships:
      | solution     | user             | roles       |
      | Klop mirubes | Plot user        | owner       |
      | Klop mirubes | Lope Facilitator | facilitator |
      | Klop mirubes | Lope Facilitator | owner       |
      | Klop mirubes | Group user       | member      |

    When I am not logged in
    And I go to the "Rally fanatics" collection
    Then I should not see the text "Please confirm your contact information to enable users to contact you"

    # Check as a moderator for a collection.
    When I am logged in as a moderator
    And I go to the "Rally fanatics" collection

    Then I should not see the text "Please confirm your contact information to enable users to contact you"
    When I go to the edit form of the "Rally fanatics" collection
    And I fill in the following:
      | E-mail | confirmation_email.man@example.com |
      | Name   | Confirmation Invisible Man         |
    And I press "Create contact information"
    And I press "Publish"
    When I go to the "Rally fanatics" collection
    Then I should see the text "Please confirm your contact information to enable users to contact you"
    Given I am logged in as "Lope Facilitator"
    And I go to the "Rally fanatics" collection
    Then I should see the text "Please confirm your contact information to enable users to contact you"
    When I click "confirm"
    Then I should see the text "A new confirmation message has been sent to confirmation_email.man@example.com"
    And a pending confirmation request for "confirmation_email.man@example.com" should exist
    But no expired confirmation request for "confirmation_email.man@example.com" should exist

    Given that the pending confirmation request for "confirmation_email.man@example.com" has expired
    Then the confirmation request for "confirmation_email.man@example.com" should be expired
    And an expired confirmation request for "confirmation_email.man@example.com" should exist
    But no pending confirmation request for "confirmation_email.man@example.com" should exist

    When I go to the "Rally fanatics" collection
    Then I should see the text "Please confirm your contact information to enable users to contact you"
    And an expired confirmation request for "confirmation_email.man@example.com" should exist
    # A new pending confirmation request has been created.
    And a pending confirmation request for "confirmation_email.man@example.com" should exist

    # The pending expiration is already in the page.
    When I click "confirm"
    Then I should see the text "A new confirmation message has been sent to confirmation_email.man@example.com"
    # The hash expiration for the confirmation request has been renewed.
    And the confirmation request for "confirmation_email.man@example.com" should not be expired

    # Confirm the confirmation request and re-check the markup.
    Given that the pending confirmation request for "confirmation_email.man@example.com" has been confirmed
    Then an accepted confirmation request for "confirmation_email.man@example.com" should exist
    And an expired confirmation request for "confirmation_email.man@example.com" should exist
    When I go to the "Rally fanatics" collection
    Then I should not see the text "Please confirm your contact information to enable users to contact you"

    Given I am logged in as "Group user"
    When I go to the "Rally fanatics" collection
    Then I should not see the text "Please confirm your contact information to enable users to contact you"

    # Check also for a solution and a facilitator.
    When I am logged in as a moderator
    And I go to the "Klop mirubes" solution
    Then I should not see the text "Please confirm your contact information to enable users to contact you"
    When I go to the edit form of the "Klop mirubes" solution
    And I fill in the following:
      | E-mail | confirmation_email2.man@example.com |
      | Name   | Confirmation Invisible Man 2        |
    And I press "Create contact information"
    And I press "Publish"
    When I go to the "Klop mirubes" solution
    Then I should see the text "Please confirm your contact information to enable users to contact you"
    Given I am logged in as "Lope Facilitator"
    And I go to the "Klop mirubes" solution
    Then I should see the text "Please confirm your contact information to enable users to contact you"
    When I click "confirm"
    Then I should see the text "A new confirmation message has been sent to confirmation_email2.man@example.com"

    # A new email can be sent in case the previous one was rejected.
    Given that the pending confirmation request for "confirmation_email2.man@example.com" has been cancelled
    When I go to the "Klop mirubes" solution
    Then I should see the text "Please confirm your contact information to enable users to contact you"
    When I click "confirm"
    Then I should see the text "A new confirmation message has been sent to confirmation_email2.man@example.com"

    # Check scenario for Member role
    Given I am logged in as "Group user"
    When I go to the "Klop mirubes" solution
    Then I should not see the text "Please confirm your contact information to enable users to contact you"

  Scenario: Test the resend functionality for a contact created through API.
    Given contact_information content:
      | name               | email               |
      | Existing to resend | existing@resend.com |
    Given solution content:
      | title                       | description          | state     | contact information |
      | Existing to resend solution | Default description. | published | Existing to resend  |

    When I am logged in as a user with the authenticated role
    And I go to the "Existing to resend solution" solution
    Then I should not see the text "Please confirm your contact information to enable users to contact you"

    When I am logged in as a moderator
    And I go to the "Existing to resend solution" solution
    Then I should see the text "Please confirm your contact information to enable users to contact you"
    When I click "confirm"
    Then I should see the text "A new confirmation message has been sent to existing@resend.com"

    # Check permissions again to ensure that the link is cached properly.
    Given I am not logged in
    When I go to the "Existing to resend solution" solution
    Then I should not see the text "Please confirm your contact information to enable users to contact you"

    Given I am logged in as a moderator
    And I go to the "Existing to resend solution" solution
    Then I should see the text "Please confirm your contact information to enable users to contact you"

    Given I am logged in as a facilitator of the "Existing to resend solution" solution
    And I go to the "Existing to resend solution" solution
    Then I should see the text "Please confirm your contact information to enable users to contact you"

    Given I am logged in as a user with the authenticated role
    And I go to the "Existing to resend solution" solution
    Then I should not see the text "Please confirm your contact information to enable users to contact you"

  Scenario: Regression test to verify permissions into multiple edge cases.
    Given contact_information content:
      | name        | email                   |
      | Some Resend | some.resend@example.com |
    Given solution content:
      | title                       | description          | state     | contact information |
      | Existing to resend solution | Default description. | published | Some Resend         |
    And the following email confirmation requests:
      | email                   | status  |
      | some.resend@example.com | pending |

    When I am logged in as a moderator
    When I go to the "Existing to resend solution" solution
    Then I should not see the link "Some Resend"
    But I should see the text "Please confirm your contact information to enable users to contact you"

    Given the following email confirmation request:
      | email                   | status    |
      | some.resend@example.com | confirmed |
      # Create a second confirmation request right after, to ensure that in the
      # database, the confirmed one is never the first or the last.
      | some.resend@example.com | cancelled |
    When I go to the "Existing to resend solution" solution
    Then I should see the link "Some Resend"
    But I should not see the text "Please confirm your contact information to enable users to contact you"
