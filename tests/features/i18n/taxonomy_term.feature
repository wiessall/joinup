@api @group-a
Feature:  The moderator is able to translate taxonomy terms.

  Scenario: The moderator is able to translate.
    Given I am logged in as a moderator

    When I click "Translate"
    Then I should see the heading "Translate"

    Given I select "Swedish" from "Language"
    And I fill in "Search" with "Complete Solution"
    And I check "Show inactive"
    And I press "Filter"

    When I fill in the 2nd occurrence of the "Singular form" field with "Komplett lösning" in the "Complete Solution" row
    And I press the 2nd button in the "Complete Solution" row
    Then I should see the success message 'Translation of source string "Complete Solution" in language Swedish was updated.'

    And I go to the "Complete Solution" solution_classification term page
    Then I should see the heading "Complete Solution"
    And I should see "Artifact (product, tool, or digital service)."
    When I click "Swedish"
    Then I should see the heading "Komplett lösning"
    And I should see "Artifact (product, tool, or digital service)."
