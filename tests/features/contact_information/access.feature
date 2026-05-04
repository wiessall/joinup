@api @group-c
Feature: Contact information access
  In order to see contextualized information
  As a visitor
  I don't want to see the contact information full page

  Scenario: Anonymous user contact information access
    Given the following "contact_information" content:
      | name         | email                   | website url       |
      | Phil Coulson | phil.coulson@shield.gov | http://shield.gov |

    When I am an anonymous user
    When I go to the "Phil Coulson" "contact information"
    Then I should see the heading "Sign in to continue"

  Scenario: Facilitator can edit contact information
    Given users:
      | Username      | Roles | E-mail                      | First name | Family name |
      | Michiel Lucas |       | michiel.lucas@one-agency.be | Michiel    | Lucas       |
    And the following "contact_information" content:
      | name | email            | author        |
      | info | info@dataflow.be | Michiel Lucas |
    And the following "collection" content:
      | title                   | contact information |
      | Ausy software solutions | info                |
    And the following collection user memberships:
      | collection              | user          | roles       |
      | Ausy software solutions | Michiel Lucas | facilitator |
    When I am logged in as "Michiel Lucas"
    When I go to the edit form of the "Ausy software solutions" collection
    Then I should see the button "Edit" in the "Contact information inline form" region
