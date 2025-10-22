@api @group-a
Feature:  The translator is able to translate

  Scenario: The translator is able to translate.
    Given I am logged in as a translator

    When I click "Menus" in the "Administration toolbar"
    And I click "List links" in the "European Commission" row
    And I click "Add link"
    And I fill in "Menu link title" with "Homepage EN"
    And I fill in "Link" with "<front>"
    And I press "Save"

    When I click "Translate"
    Then I should see the heading "Translate"

    Given I select "Swedish" from "Language"
    And I fill in "Search" with "Homepage EN"
    And I check "Show inactive"
    And I press "Filter"

    When I fill in the "2nd" occurrence of the "Singular form" field with "Homepage SV" in the "Homepage EN" row
    And I press the 2nd button in the "Homepage EN" row
    Then I should see the success message 'Translation of source string "Homepage EN" in language Swedish was updated.'

    And I visit "/"
    Then I should see the link "Homepage EN" in the "Footer"
    And I visit "/sv"
    Then I should see the link "Homepage SV" in the "Footer"
