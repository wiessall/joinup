@api @group-clone
Feature: Test share functionality for solution.

  Scenario: Check share functionality for solution
    Given I visit "/collection/spain-center-technology-transfer/solution/electronic-headquarters-type-government-canary-islands"
    And I click "Share"
    Then a modal should open
    And I should see the following lines of text:
      | Share on               |
      | Facebook               |
      | Twitter                |
      | Linkedin               |
