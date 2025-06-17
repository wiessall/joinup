@api @group-a
Feature:  The translator is able to translate

  Scenario: The translator is able to translate.
    Given I am logged in as a translator
    When I click "Menus" in the "Administration toolbar"
    And I click "List links" in the "European Commission" row
    And I click "Add link"
    And I fill in "Menu link title" with "Homepage en"
    And I fill in "Link" with "<front>"
    And I press "Save"
    And I click "Edit" in the "Homepage en" row

    When I click "Translate"
    And I click "Add" in the "Swedish" row
    And I fill in "Menu link title" with "Homepage sv"
    And I press "Save"
    And I visit "/"
    Then I should see the link "Homepage en" in the "Footer"
    And I visit "/sv"
    Then I should see the link "Homepage sv" in the "Footer"
