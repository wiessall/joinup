@api @group-h @javascript
Feature: Pinning discussion entities
  As a facilitator of a solution/collection
  I want to pin content at the top of the discussions page
  So that important content has more visibility

  Background:
    Given the following collections:
      | title                   | state     |
      | Green Wrench collection | published |
    Given the following solutions:
      | title                | state     |
      | Blue Wrench solution | published |

  Scenario Outline: Pinned discussions should show a visual cue in discussions page.
    Given discussion content:
      | title       | headline    | solution   | collection   | state     | pinned |
      | Lantern FAQ | Lantern FAQ | <solution> | <collection> | published | yes    |
      | Lantern TCA | Lantern TCA | <solution> | <collection> | published | no     |

    When I go to the discussions page of "<parent>"
    Then the "Lantern FAQ" tile should be marked as pinned
    But the "Lantern TCA" tile should not be marked as pinned

    When I am at "/search"
    Then the "Lantern FAQ" tile should not be marked as pinned

    # Verify that changes in the pinned state are reflected to the tile.
    When I am logged in as a facilitator of the "<parent>" <type>
    When I go to the discussions page of "<parent>"
    Then the "Lantern FAQ" tile should be marked as pinned
    But the "Lantern TCA" tile should not be marked as pinned

    When I click the contextual link "Pin" in the "Lantern TCA" tile
    Then the "Lantern TCA" tile should be marked as pinned
    And the "Lantern FAQ" tile should be marked as pinned

    When I click the contextual link "Unpin" in the "Lantern FAQ" tile
    Then the "Lantern FAQ" tile should not be marked as pinned
    And the "Lantern TCA" tile should be marked as pinned

    Examples:
      | solution             | collection              | parent                  | type       |
      | Blue Wrench solution |                         | Blue Wrench solution    | solution   |
      |                      | Green Wrench collection | Green Wrench collection | collection |
