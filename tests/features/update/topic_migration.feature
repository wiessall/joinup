@group-clone @api
Feature: Test the migration of the topics.

  Scenario: Test a created topic.
    Given I go to the "Smart cities" term page
    # The fact that we are on the search page and not a 404 page is enough to
    # ensure that the topic has been migrated.
    Then I should see the heading "Advanced search"
    Given I go to the "eJustice and law" term page
    Then I should see the heading "Advanced search"
    Given I go to the "Digital ready policymaking" term page
    Then I should see the heading "Advanced search"
    Given I go to the "Tools" term page
    Then I should see the heading "Advanced search"

  Scenario: Test renamed topic.
    Given I go to the "Free and open source software" term page
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
