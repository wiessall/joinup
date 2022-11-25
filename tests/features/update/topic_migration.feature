@group-clone @api
Feature: Test the migration of the topics.

  Scenario: Test a created topic.
    Given I go to the "Smart cities" term page
    Then I should see the heading "Smart cities"

    Given I go to the "eJustice and law" term page
    Then I should see the heading "eJustice and law"

  Scenario: Test renamed topic.
    Given I go to the "Free and open source software" term page
    Then I should see the heading "Free and open source software"

    Given I go to the "Culture and education" term page
    Then I should see the heading "Culture and education"

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
