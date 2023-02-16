@group-clone @api
Feature: Test the migration of the topics.

  Scenario Outline: Test a created topic.
    Given I go to the "<term name>" term page
    # The fact that we are on the search page and not a 404 page is enough to
    # ensure that the topic has been migrated.
    Then I should see the heading "Advanced search"
    Examples:
      | term name                    |
      | Smart cities                 |
      | Observatories                |
      | Reusable Software Components |
      | Innovation                   |

  Scenario: Test renamed topic.
    Given I go to the "Open Source Software" term page
    # The fact that we are on the search page and not a 404 page is enough to
    # ensure that the topic has been renamed (loaded by name).
    Then I should see the heading "Advanced search"

    Given I go to the "Culture and education" term page
    Then I should see the heading "Advanced search"

   Scenario: Test entities moved to a new topic.
     Given I go to the "PDF-Over" solution
     Then I should see the text "eIdentity and eSignature" in the "Header" region
     And I should see the text "ICT security" in the "Header" region
     # Entities referencing the "Defence" topic were moved to "ICT security" topic.
     And I should not see the text "Defence" in the "Header" region

     Given I am logged in as a moderator
     And I go to the "Human-Centricity in Digital Delivery: Enhancing Agile Governance" news
     And I click "Edit"
     Then the option "-eGovernment" should be selected
