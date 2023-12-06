@api @group-clone
Feature: Post update tests for ISAICP-8220.

  Scenario: Check publish date is not anymore in the future, 2038.

    Given I am an anonymous user
    And I visit "/collection/better-legislation-smoother-implementation/news/key-takeaways-february-2023-virtual-breakfast"
    Then I should not see the text "19/01/2038"
    And I should see the text "08/02/2023"

    Given I am logged in as a moderator
    And I visit "/collection/joinup/news/welcome-joinup"
    Then I should not see the text "19/01/2038"
    And I should see the text "12/07/2018"
