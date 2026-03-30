@api @group-a
Feature: The moderator is able to translate meta entity strings.

  Scenario: The moderator is able to translate collection settings strings.
    Given collection content:
      | title          |
      | Dragon Keepers |
    And I am logged in as a moderator
    And I go to the homepage of the "Dragon Keepers" collection
    And I click "Settings" in the "Entity actions" region
    And I fill in "Join label" with "Join the dragons"
    And I fill in "Leave label" with "Leave the dragons"
    And I press "Save"

    When I click "Translate" in the "Administration toolbar"
    Then I should see the heading "Translate"

    Given I select "Polish" from "Language"
    And I fill in "Search" with "the dragons"
    And I check "Show inactive"
    And I press "Filter"

    When I fill in the 2nd occurrence of the "Singular form" field with "Dołącz do smoków" in the "Join the dragons" row
    And I press the 2nd button in the "Join the dragons" row
    Then I should see the success message 'Translation of source string "Join the dragons" in language Polish was updated.'

    When I fill in the 2nd occurrence of the "Singular form" field with "Opuść smoki" in the "Leave the dragons" row
    And I press the 2nd button in the "Leave the dragons" row
    Then I should see the success message 'Translation of source string "Leave the dragons" in language Polish was updated.'

    And I go to the homepage of the "Dragon Keepers" collection
    When I click "Polish"
    When I press "Dołącz do smoków" in the Header region
    And I press "Confirm"
    And I should see the text "Opuść smoki"

  Scenario: The moderator is able to translate EIF perspective strings.
    Given solution content:
      | title            |
      | EIF Test Machine |
    And the following EIF perspective data for the "EIF Test Machine" solution:
      | Description | A great interoperability perspective. |
    And I am logged in as a moderator

    When I click "Translate" in the "Administration toolbar"
    Then I should see the heading "Translate"

    Given I select "Polish" from "Language"
    And I fill in "Search" with "A great interoperability perspective."
    And I check "Show inactive"
    And I press "Filter"

    When I fill in the 2nd occurrence of the "Singular form" field with "Dobra perspektywa." in the "A great interoperability perspective." row
    And I press the 2nd button in the "A great interoperability perspective." row
    Then I should see the success message 'Translation of source string "A great interoperability perspective., content_editor" in language Polish was updated.'

    And I go to the EIF perspective page of the "EIF Test Machine" solution
    When I click "Polish"
    Then I should see the text "Dobra perspektywa."
