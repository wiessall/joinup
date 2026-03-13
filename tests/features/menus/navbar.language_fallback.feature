@api @group-h
Feature: The first content language fallback should be English.
  This also affects other entities, menu links were only the first entities
  where we noticed an issue.

  Scenario: Menu link content uses English language as first fallback.
    Given users:
      | Username    | Roles     |
      | John de Mod | moderator |
    Given I am logged in as "John de Mod"
    When I go to "/admin/structure/menu/manage/navbar"
    And I click "Add link"
    And I fill in "Menu link title" with "[EN] Navbar language fallback test"
    And I fill in "Link" with "<front>"
    And I press "Save"
    Then I click "Translate" in the "[EN] Navbar language fallback test" row
    And I click "Add" in the "Bulgarian" row
    And I fill in "Menu link title" with "[BG] Navbar language fallback test"
    And I press "Save"
    And I go to "/"
    Then I should see the link "[EN] Navbar language fallback test"
    And I go to "/bg/"
    Then I should see the link "[BG] Navbar language fallback test"
    And I go to "/fr/"
    Then I should see the link "[EN] Navbar language fallback test"
