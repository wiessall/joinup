@api @group-a
Feature: Creating nodes in foreign language
  This test covers some common translation features.
  Nodes are creating manually in different languages
  (language select) and then the source is checked.

  Scenario: Create node in polish language
    Given I am logged in as a developer
    When I go to the propose collection form
    Then I should see the heading "Propose collection"
    When I fill in the following:
      | Title       | Kolekcja               |
      | Abstract    | Krótki opis            |
      | Description | Długi opis             |
      | Name        | Overviewer contact     |
      | E-mail      | overviewer@example.com |
    And I select "Employment and Support Allowance" options from "Topic"
    And I attach the file "logo.png" to "Logo"
    And I press "Add existing" at the "Owner" field
    And I fill in "Owner" with "ISA²"
    And I press "Add owner"
    And I fill in "Alternative text" with "Tekst logotypu"
    And I select "Polish" from "Language"
    And I press "Publish"
    When I click "Translate" in the "Entity actions" region
    Then I should see "Polish (Original language)"
    And I should see "Kolekcja" in the "Polish (Original language)" row
    And I should see "Source" in the "Polish (Original language)" row
