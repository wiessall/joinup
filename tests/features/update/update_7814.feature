@api @group-clone
Feature: Post update tests for ISAICP-7814.

  Scenario: Moderators can now see 'Published' as the current workflow state.
    Given I am logged in as a moderator
    When I go to the edit form of the "gvSIG Desktop 2.5.1(03-11-2020)" news
    Then I should see the text "Published"
    # Ensure it is listed in the search results too.
    When I am on the homepage
    Then I enter "gvSIG Desktop 2.5.1(03-11-2020)" in the search bar and press enter
    Then I should see the "gvSIG Desktop 2.5.1(03-11-2020)" tile

    Given I go to the edit form of the "Science for Innovations in Public Governance" collection
    Then I should see the text "Published"
    # Ensure it is listed in the search results too.
    When I am on the homepage
    Then I enter "Science for Innovations in Public Governance" in the search bar and press enter
    Then I should see the "Science for Innovations in Public Governance" tile

 Scenario: The doublue 'Published' entry does not show in 'Manage content'.
   Given I am logged in as a moderator
   And I go to the "Re3gistry" solution
   And I click "Manage content"
   Then the response should contain "<option value=\"published\">Published</option>"
   But the response should not contain "<option value=\"validated\">Published</option>"
