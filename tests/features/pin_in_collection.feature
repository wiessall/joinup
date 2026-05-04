@api
Feature: Pinning entities inside collections
  As a facilitator of a collection
  I want to pin entities at the top of the collection homepage
  So that important entities has more visibility

  Background:
    Given the following "collection" content:
      | title         | creation date | modification date |
      | Orange Wrench | 2018-10-15    | 2019-10-15T23:03  |
      | Green Wrench  | 2018-11-05    | 2019-11-05T23:03  |
    And the following "solution" content:
      | title            | collection    | pinned in     | creation date | shared on     |
      | Wrench catalogue | Orange Wrench | Orange Wrench | 2017-10-12    |               |
    And the following "discussion" content:
      | title                        | collection    | pinned | created    | changed    |
      | What kind of wrench is this? | Orange Wrench | no     | 2017-11-20 | 2018-11-20 |
    And the following users:
      | Username       | E-mail                     |
      | Rozanne Minett | rozanne.minett@example.com |
      | Tyron Ingram   | tyron.ingram@example.com   |
      | Andy Cross     | andy.cross@example.com     |
    And the following collection user memberships:
      | collection    | user           | roles       |
      | Orange Wrench | Rozanne Minett | facilitator |
      | Orange Wrench | Tyron Ingram   |             |
      | Green Wrench  | Rozanne Minett | facilitator |
      | Green Wrench  | Andy Cross     | facilitator |
      | Green Wrench  | Tyron Ingram   |             |

  Scenario Outline: Facilitators can pin and unpin community content inside their collections.
    And the following "<content type>" content:
      | title              | collection    | pinned |
      | Very important     | Orange Wrench | yes    |
      | Useful information | Orange Wrench | no     |

    When I am an anonymous user
    And I go to the homepage of the "Orange Wrench" collection
    Then I should see the following tiles in the correct order:
      | Very important     |
      | Wrench catalogue   |
      | Useful information |
    Then I should not see the contextual links "Pin, Unpin" in the "Useful information" tile
    And I should not see the contextual links "Pin, Unpin" in the "Very important" tile

    When I am logged in as an "authenticated user"
    And I go to the homepage of the "Orange Wrench" collection
    Then I should not see the contextual links "Pin, Unpin" in the "Useful information" tile
    And I should not see the contextual links "Pin, Unpin" in the "Very important" tile

    # Members and facilitators of other collections cannot pin nor unpin.
    When I am logged in as "Andy Cross"
    And I go to the homepage of the "Orange Wrench" collection
    Then I should not see the contextual links "Pin, Unpin" in the "Useful information" tile
    And I should not see the contextual links "Pin, Unpin" in the "Very important" tile

    # Collection members cannot pin nor unpin content.
    When I am logged in as "Tyron Ingram"
    And I go to the homepage of the "Orange Wrench" collection
    Then I should not see the contextual links "Pin, Unpin" in the "Useful information" tile
    And I should not see the contextual links "Pin, Unpin" in the "Very important" tile

    # Facilitators of the collection the content belongs to can pin/unpin.
    When I am logged in as "Rozanne Minett"
    And I go to the homepage of the "Orange Wrench" collection
    Then I should see the contextual link "Pin" in the "Useful information" tile
    And I should see the contextual link "Unpin" in the "Very important" tile
    But I should not see the contextual link "Unpin" in the "Useful information" tile
    And I should not see the contextual link "Pin" in the "Very important" tile

    When I click the contextual link "Unpin" in the "Very important" tile
    Then I should see the success message "<label> Very important has been unpinned in the collection Orange Wrench."
    And I should see the following tiles in the correct order:
      | Wrench catalogue   |
      | Useful information |
      | Very important     |
    And I should see the contextual link "Pin" in the "Very important" tile
    But I should not see the contextual link "Unpin" in the "Very important" tile

    When I click the contextual link "Pin" in the "Useful information" tile
    Then I should see the success message "<label> Useful information has been pinned in the collection Orange Wrench."
    And I should see the following tiles in the correct order:
      | Useful information |
      | Wrench catalogue   |
      | Very important     |
    And I should see the contextual link "Unpin" in the "Useful information" tile
    But I should not see the contextual link "Pin" in the "Useful information" tile

    # When we are not in a collection page, the pin/unpin links should not be visible.
    When I am at "/search"
    Then I should not see the contextual links "Pin, Unpin" in the "Useful information" tile
    And I should not see the contextual links "Pin, Unpin" in the "Very important" tile

    Examples:
      | content type | label    |
      | event        | Event    |
      | document     | Document |
      | news         | News     |
      | solution     | Solution |

  @javascript
  Scenario: Last update time of a solution is not affected by (un)pinning.
    Given I am logged in as "Rozanne Minett"

    # Pinning and unpinning items should not affect the "last update" timestamp.
    # Before changing the pinned status, let's check that the solution is in the
    # expected position when searching for content ordered by last updated time.
    When I visit the advanced search page
    And I select "Last update" from "Sort by"
    And I enter "Wrench" in the search bar and press enter
    Then I should see the following tiles in the correct order:
      | Wrench catalogue             |
      | Green Wrench                 |
      | Orange Wrench                |
      | What kind of wrench is this? |

    When I go to the homepage of the "Orange Wrench" collection
    When I click the contextual link "Unpin" in the "Wrench catalogue" tile
    Then I should see the success message "Solution Wrench catalogue has been unpinned in the collection Orange Wrench."

    When I visit the advanced search page
    And I select "Last update" from "Sort by"
    And I enter "Wrench" in the search bar and press enter
    Then I should see the following tiles in the correct order:
      | Wrench catalogue             |
      | Green Wrench                 |
      | Orange Wrench                |
      | What kind of wrench is this? |

    When I go to the homepage of the "Orange Wrench" collection
    When I click the contextual link "Pin" in the "Wrench catalogue" tile
    Then I should see the success message "Solution Wrench catalogue has been pinned in the collection Orange Wrench."

    # Check that the "last update" timestamp has not been affected. We can check
    # this in the search page.
    When I visit the advanced search page
    And I select "Last update" from "Sort by"
    And I enter "Wrench" in the search bar and press enter
    Then I should see the following tiles in the correct order:
      | Wrench catalogue             |
      | Green Wrench                 |
      | Orange Wrench                |
      | What kind of wrench is this? |

  @javascript
  Scenario Outline: Pinned content tiles should show a visual cue only in their collection homepage.
    Given the following "<content type>" content:
      | title         | headline      | collection    | pinned | shared on    |
      | Lantern FAQs  | Lantern FAQs  | Orange Wrench | yes    | Green Wrench |
      | Lantern terms | Lantern terms | Orange Wrench | no     |              |

    When I go to the homepage of the "Orange Wrench" collection
    Then the "Lantern FAQs" tile should be marked as pinned
    But the "Lantern terms" tile should not be marked as pinned

    # When shared on other collection, content shouldn't show the pin icon.
    When I go to the homepage of the "Green Wrench" collection
    Then the "Lantern FAQs" tile should not be marked as pinned

    When I am at "/search"
    Then the "Lantern FAQs" tile should not be marked as pinned

    # Verify that changes in the pinned state are reflected to the tile.
    When I am logged in as a facilitator of the "Orange Wrench" collection
    When I go to the homepage of the "Orange Wrench" collection
    Then the "Lantern FAQs" tile should be marked as pinned
    But the "Lantern terms" tile should not be marked as pinned

    When I click the contextual link "Pin" in the "Lantern terms" tile
    Then the "Lantern terms" tile should be marked as pinned
    And the "Lantern FAQs" tile should be marked as pinned

    When I click the contextual link "Unpin" in the "Lantern FAQs" tile
    Then the "Lantern FAQs" tile should not be marked as pinned
    And the "Lantern terms" tile should be marked as pinned

    Examples:
      | content type |
      | event        |
      | document     |
      | news         |
