@api @group-e
Feature: View discussion API
  As a visitor of the website I can view a discussion content.

  Scenario: Programmatically create a discussion
    And collection content:
      | title                | state     |
      | Parallel programming | published |
    Given discussion content:
      | title                | content                  | collection           | state     |
      | Fearless concurrency | Let us have a discussion | Parallel programming | published |
    Then I should have a "Discussion" page titled "Fearless concurrency"

    # Check that the basic fields are visible.
    When I go to the "Fearless concurrency" discussion
    Then I should see the text "Parallel programming"
    And I should see the heading "Fearless concurrency"
    And I should see the text "Let us have a discussion"
    # The discussion does not have a file attached to it, so it should not show
    # the heading of the attachments list.
    But I should not see the text "Attachments"
