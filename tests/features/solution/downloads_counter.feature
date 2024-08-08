@api @group-g
Feature:
  Check downloads counter's value at different places.

  Scenario: Check solution downloads counter
    # Create the dummy data to work with.
    Given the following collection:
      | title | Ocean studies |
    Given the following solution:
      | title       | Climate change tracker                            |
      | description | Atlantic salmon arrived after the Little Ice Age. |
      | collection  | Ocean studies                                     |
      | state       | published                                         |
    Given distribution content:
      | title                 | description          | solution               | downloads | state     |
      | Sample distribution   | Sample description   | Climate change tracker | 10        | published |
      | Sample distribution 2 | Sample description 2 | Climate change tracker | 0         | published |
    # Checks solutions tiles to we have the correct numbers.
    When I visit the solution overview
    Then I should see the "Climate change tracker" tile
    And I should see the statistics "10" in the "Climate change tracker" tile
    When I go to the homepage of the "Climate change tracker" solution
    # Check the same thing on the homepage of the solutions.
    Then I should not see the text "Downloads: 10"
    When I delete the "Sample distribution" distribution
    And I reload the page
    # Check it again after the first distribution is deleted and cache has been cleared.
    Then I should not see the text "Downloads: 10"
    # Go back to the solution tiles page and check downloads are disappeared.
    When I visit the solution overview
    Then the download icon should not be shown in the "Climate change tracker" tile
    Given distribution content:
      | title                 | description          | solution               | downloads | state     |
      | Sample distribution 3 | Sample description 3 | Climate change tracker | 20        | published |
    When I reload the page
    Then the "Climate change tracker" tile should show 20 downloads
