@api
Feature: Pinning entities inside collections
  As a facilitator of a collection
  I want to pin entities at the top of the collection homepage
  So that important entities has more visibility

  Background:
    Given the following collections:
      | title         | state     |
      | Orange Wrench | published |
      | Cloudy Beam   | published |
      | Green Wrench  | published |
    And the following solution:
      | title | Space Silver |
      | state | published    |
    And users:
      | Username        | E-mail                      |
      | Rozanne Minett  | rozanne.minett@example.com  |
      | Tyron Ingram    | tyron.ingram@example.com    |
      | Andy Cross      | andy.cross@example.com      |
      | Xanthia Gilbert | xanthia.gilbert@example.com |
    And the following collection user memberships:
      | collection    | user            | roles       |
      | Orange Wrench | Rozanne Minett  | facilitator |
      | Cloudy Beam   | Rozanne Minett  | facilitator |
      | Orange Wrench | Tyron Ingram    |             |
      | Green Wrench  | Rozanne Minett  | facilitator |
      | Cloudy Beam   | Andy Cross      | facilitator |
      | Cloudy Beam   | Xanthia Gilbert |             |
      | Green Wrench  | Tyron Ingram    |             |

  Scenario Outline: Facilitators can pin and unpin community content inside their collections.
    Given news content:
      | title                       | collection    | state     |
      | What is the HEX for orange? | Orange Wrench | published |
    And <content type> content:
      | title              | headline           | collection    | state     | pinned |
      | Very important     | Important very     | Orange Wrench | published | yes    |
      | Useful information | Information useful | Orange Wrench | published | no     |

    When I am an anonymous user
    And I go to the homepage of the "Orange Wrench" collection
    Then I should see the following tiles in the correct order:
      | Very important              |
      | What is the HEX for orange? |
      | Useful information          |
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
    When I am logged in as "Xanthia Gilbert"
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
    # Todo: Due to an environment related issue on CPHP this is causing random
    #   failures which cannot be replicated in production. Re-enable this check
    #   once we have updated to a more recent version of Solr. See ISAICP-6245.
    # And I should see the following tiles in the correct order:
    #   | What is the HEX for lemon? |
    #   | Useful information         |
    #   | Very important             |
    And I should see the contextual link "Pin" in the "Very important" tile
    But I should not see the contextual link "Unpin" in the "Very important" tile

    When I click the contextual link "Pin" in the "Useful information" tile
    Then I should see the success message "<label> Useful information has been pinned in the collection Orange Wrench."
    # Todo: Due to an environment related issue on CPHP this is causing random
    #   failures which cannot be replicated in production. Re-enable this check
    #   once we have updated to a more recent version of Solr. See ISAICP-6245.
    # And I should see the following tiles in the correct order:
    #   | Useful information         |
    #   | What is the HEX for lemon? |
    #   | Very important             |
    And I should see the contextual link "Unpin" in the "Useful information" tile
    But I should not see the contextual link "Pin" in the "Useful information" tile

    # When we are not in a collection page, the pin/unpin links should not be visible.
    When I am at "/search"
    Then I should not see the contextual links "Pin, Unpin" in the "Useful information" tile
    And I should not see the contextual links "Pin, Unpin" in the "Very important" tile

    Examples:
      | content type | label      |
      | event        | Event      |
      | document     | Document   |

  Scenario: Facilitators can pin and unpin community news content inside their collections.
    Given news content:
      | title                      | collection   | state     |
      | What is the HEX for green? | Green Wrench | published |
    And news content:
      | title              | headline           | collection   | state     | pinned |
      | Very important     | Important very     | Green Wrench | published | yes    |
      | Useful information | Information useful | Green Wrench | published | no     |

    When I am an anonymous user
    And I go to the homepage of the "Green Wrench" collection
    Then I should see the following tiles in the correct order:
      | Very important             |
      | What is the HEX for green? |
      | Useful information         |
    Then I should not see the contextual links "Pin, Unpin" in the "Useful information" tile
    And I should not see the contextual links "Pin, Unpin" in the "Very important" tile

    When I am logged in as an "authenticated user"
    And I go to the homepage of the "Green Wrench" collection
    Then I should not see the contextual links "Pin, Unpin" in the "Useful information" tile
    And I should not see the contextual links "Pin, Unpin" in the "Very important" tile

    # Members and facilitators of other collections cannot pin nor unpin.
    When I am logged in as "Andy Cross"
    And I go to the homepage of the "Green Wrench" collection
    Then I should not see the contextual links "Pin, Unpin" in the "Useful information" tile
    And I should not see the contextual links "Pin, Unpin" in the "Very important" tile
    When I am logged in as "Xanthia Gilbert"
    And I go to the homepage of the "Green Wrench" collection
    Then I should not see the contextual links "Pin, Unpin" in the "Useful information" tile
    And I should not see the contextual links "Pin, Unpin" in the "Very important" tile

    # Collection members cannot pin nor unpin content.
    When I am logged in as "Tyron Ingram"
    And I go to the homepage of the "Green Wrench" collection
    Then I should not see the contextual links "Pin, Unpin" in the "Useful information" tile
    And I should not see the contextual links "Pin, Unpin" in the "Very important" tile

    # Facilitators of the collection the content belongs to can pin/unpin.
    When I am logged in as "Rozanne Minett"
    And I go to the homepage of the "Green Wrench" collection
    Then I should see the contextual link "Pin" in the "Useful information" tile
    And I should see the contextual link "Unpin" in the "Very important" tile
    But I should not see the contextual link "Unpin" in the "Useful information" tile
    And I should not see the contextual link "Pin" in the "Very important" tile

    When I click the contextual link "Unpin" in the "Very important" tile
    Then I should see the success message "News Very important has been unpinned in the collection Green Wrench."
    And I should see the contextual link "Pin" in the "Very important" tile
    But I should not see the contextual link "Unpin" in the "Very important" tile

    When I click the contextual link "Pin" in the "Useful information" tile
    Then I should see the success message "News Useful information has been pinned in the collection Green Wrench."
    And I should see the contextual link "Unpin" in the "Useful information" tile
    But I should not see the contextual link "Pin" in the "Useful information" tile

    # When we are not in a collection page, the pin/unpin links should not be visible.
    When I am at "/search"
    Then I should not see the contextual links "Pin, Unpin" in the "Very important" tile
    And I should not see the contextual links "Pin, Unpin" in the "Useful information" tile

  Scenario: Facilitators can pin and unpin solutions inside their collections.
    Given news content:
      | title                         | collection    | state     | pinned | created    |
      | Where can I find this wrench? | Orange Wrench | published | no     | 2017-11-20 |
      | Any thoughts about blue?      | Orange Wrench | published | yes    | 2017-10-03 |
      | Multi stratus beaming         | Cloudy Beam   | published | no     | 2017-11-05 |
    And solutions:
      | title            | collection                 | state     | pinned in     | creation date |
      | Wrench catalogue | Orange Wrench              | published | Orange Wrench | 2017-10-12    |
      | Orange estimator | Orange Wrench, Cloudy Beam | published |               | 2017-10-02    |

    When I am an anonymous user
    And I go to the homepage of the "Orange Wrench" collection
    Then I should see the following tiles in the correct order:
      | Wrench catalogue              |
      | Any thoughts about blue?      |
      | Where can I find this wrench? |
      | Orange estimator              |
    Then I should not see the contextual links "Pin, Unpin" in the "Orange estimator" tile
    And I should not see the contextual links "Pin, Unpin" in the "Wrench catalogue" tile

    When I am logged in as an "authenticated user"
    And I go to the homepage of the "Orange Wrench" collection
    Then I should not see the contextual links "Pin, Unpin" in the "Orange estimator" tile
    And I should not see the contextual links "Pin, Unpin" in the "Wrench catalogue" tile

    # Members and facilitators of other collections cannot pin nor unpin.
    When I am logged in as "Andy Cross"
    And I go to the homepage of the "Orange Wrench" collection
    Then I should not see the contextual links "Pin, Unpin" in the "Orange estimator" tile
    And I should not see the contextual links "Pin, Unpin" in the "Wrench catalogue" tile
    When I am logged in as "Xanthia Gilbert"
    And I go to the homepage of the "Orange Wrench" collection
    Then I should not see the contextual links "Pin, Unpin" in the "Orange estimator" tile
    And I should not see the contextual links "Pin, Unpin" in the "Wrench catalogue" tile

    # Collection members cannot pin nor unpin content.
    When I am logged in as "Tyron Ingram"
    And I go to the homepage of the "Orange Wrench" collection
    Then I should not see the contextual links "Pin, Unpin" in the "Orange estimator" tile
    And I should not see the contextual links "Pin, Unpin" in the "Wrench catalogue" tile

    # Facilitators of the collection the content belongs to can pin/unpin.
    When I am logged in as "Rozanne Minett"
    And I go to the homepage of the "Orange Wrench" collection
    Then I should see the contextual link "Pin" in the "Orange estimator" tile
    And I should see the contextual link "Unpin" in the "Wrench catalogue" tile
    But I should not see the contextual link "Unpin" in the "Orange estimator" tile
    And I should not see the contextual link "Pin" in the "Wrench catalogue" tile

    When I go to the homepage of the "Cloudy Beam" collection
    Then I should see the following tiles in the correct order:
      | Multi stratus beaming |
      | Orange estimator      |
    And I should see the contextual link "Pin" in the "Orange estimator" tile
    And I should not see the contextual link "Unpin" in the "Orange estimator" tile

    When I go to the homepage of the "Orange Wrench" collection
    When I click the contextual link "Unpin" in the "Wrench catalogue" tile
    Then I should see the success message "Solution Wrench catalogue has been unpinned in the collection Orange Wrench."
    And I should see the following tiles in the correct order:
      | Any thoughts about blue?      |
      | Where can I find this wrench? |
      | Wrench catalogue              |
      | Orange estimator              |
    And I should see the contextual link "Pin" in the "Wrench catalogue" tile
    But I should not see the contextual link "Unpin" in the "Wrench catalogue" tile

    When I click the contextual link "Pin" in the "Orange estimator" tile
    Then I should see the success message "Solution Orange estimator has been pinned in the collection Orange Wrench."
    And I should see the following tiles in the correct order:
      | Any thoughts about blue?      |
      | Orange estimator              |
      | Where can I find this wrench? |
      | Wrench catalogue              |
    And I should see the contextual link "Unpin" in the "Orange estimator" tile
    But I should not see the contextual link "Pin" in the "Orange estimator" tile

    # Pinning a solution in a collection shouldn't affect the other collections it is affiliated with.
    When I go to the homepage of the "Cloudy Beam" collection
    Then I should see the following tiles in the correct order:
      | Multi stratus beaming |
      | Orange estimator      |
    And I should see the contextual link "Pin" in the "Orange estimator" tile
    And I should not see the contextual link "Unpin" in the "Orange estimator" tile

    When I click the contextual link "Pin" in the "Orange estimator" tile
    Then I should see the success message "Solution Orange estimator has been pinned in the collection Cloudy Beam."
    And I should see the following tiles in the correct order:
      | Orange estimator      |
      | Multi stratus beaming |
    And I should see the contextual link "Unpin" in the "Orange estimator" tile
    And I should not see the contextual link "Pin" in the "Orange estimator" tile

    # When we are not in a collection page, the pin/unpin links should not be visible.
    When I am at "/search"
    Then I should not see the contextual links "Pin, Unpin" in the "Wrench catalogue" tile
    And I should not see the contextual links "Pin, Unpin" in the "Orange estimator" tile

  @javascript
  Scenario: Last update time of a solution is not affected by (un)pinning.
    Given the following collections:
      | title              | state     | creation date | modification date |
      | Orange Screwdriver | published | 2018-10-15    | 2019-10-15T23:03  |
      | Green Screwdriver  | published | 2018-11-05    | 2019-11-05T23:03  |
    Given discussion content:
      | title                             | collection         | state     | pinned | created    | changed    |
      | What kind of screwdriver is this? | Orange Screwdriver | published | no     | 2017-11-20 | 2018-11-20 |
    Given solutions:
      | title                    | collection         | state     | pinned in          | creation date | modification date |
      | Drop forged screwdrivers | Orange Screwdriver | published | Orange Screwdriver | 2017-10-12    | 2018-10-12T23:03  |
    And the following collection user memberships:
      | collection         | user           | roles       |
      | Orange Screwdriver | Rozanne Minett | facilitator |
      | Green Screwdriver  | Rozanne Minett | facilitator |
    Given I am logged in as "Rozanne Minett"

    # Pinning and unpinning items should not affect the "last update" timestamp.
    # Before changing the pinned status, let's check that the solution is in the
    # expected position when searching for content ordered by last updated time.
    When I visit the search page
    And I select "Last Updated Date" from "Sort by"
    And I enter "screwdriver" in the search bar and press enter
    Then I should see the following tiles in the correct order:
      | Green Screwdriver                 |
      | Orange Screwdriver                |
      | What kind of screwdriver is this? |
      | Drop forged screwdrivers          |

    When I go to the homepage of the "Orange Screwdriver" collection
    When I click the contextual link "Unpin" in the "Drop forged screwdrivers" tile
    Then I should see the success message "Solution Drop forged screwdrivers has been unpinned in the collection Orange Screwdriver."

    When I visit the search page
    And I select "Last Updated Date" from "Sort by"
    And I enter "screwdriver" in the search bar and press enter
    Then I should see the following tiles in the correct order:
      | Green Screwdriver                 |
      | Orange Screwdriver                |
      | What kind of screwdriver is this? |
      | Drop forged screwdrivers          |

    When I go to the homepage of the "Orange Screwdriver" collection
    When I click the contextual link "Pin" in the "Drop forged screwdrivers" tile
    Then I should see the success message "Solution Drop forged screwdrivers has been pinned in the collection Orange Screwdriver."

    # Check that the "last update" timestamp has not been affected. We can check
    # this in the search page.
    When I visit the search page
    And I select "Last Updated Date" from "Sort by"
    And I enter "screwdriver" in the search bar and press enter
    Then I should see the following tiles in the correct order:
      | Green Screwdriver                 |
      | Orange Screwdriver                |
      | What kind of screwdriver is this? |
      | Drop forged screwdrivers          |

  @javascript
  Scenario Outline: Pinned content tiles should show a visual cue only in their collection homepage.
    Given <content type> content:
      | title         | headline      | collection    | state     | pinned | shared on   |
      | Lantern FAQs  | Lantern FAQs  | Orange Wrench | published | yes    | Cloudy Beam |
      | Lantern terms | Lantern terms | Orange Wrench | published | no     |             |

    When I go to the homepage of the "Orange Wrench" collection
    Then the "Lantern FAQs" tile should be marked as pinned
    But the "Lantern terms" tile should not be marked as pinned

    # When shared on other collection, content shouldn't show the pin icon.
    When I go to the homepage of the "Cloudy Beam" collection
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

  @javascript
  Scenario: Pinned solutions tiles should show a visual cue only in their collection homepage.
    Given solutions:
      | title             | collection                 | state     | pinned in     |
      | Positive sunshine | Orange Wrench              | published | Orange Wrench |
      # Note: it is not yet possible to affiliate a solution to multiple collections from the UI.
      | Fast lightning    | Orange Wrench, Cloudy Beam | published | Cloudy Beam   |

    When I go to the homepage of the "Orange Wrench" collection
    Then the "Positive sunshine" tile should be marked as pinned
    But the "Fast lightning" tile should not be marked as pinned

    When I go to the homepage of the "Cloudy Beam" collection
    Then the "Fast lightning" tile should be marked as pinned

    When I am at "/search"
    Then the "Positive sunshine" tile should not be marked as pinned
    And the "Fast lightning" tile should not be marked as pinned

    # Verify that changes in the pinned state are reflected to the tile.
    When I am logged in as "Rozanne Minett"
    And I go to the homepage of the "Orange Wrench" collection
    And I click the contextual link "Pin" in the "Fast lightning" tile
    Then the "Fast lightning" tile should be marked as pinned
    And the "Positive sunshine" tile should be marked as pinned

    When I click the contextual link "Unpin" in the "Positive sunshine" tile
    Then the "Positive sunshine" tile should not be marked as pinned
    And the "Fast lightning" tile should be marked as pinned

    # Unpinning a solution from one of the collections it is affiliated with
    # should not affect the state in other collections.
    When I go to the homepage of the "Cloudy Beam" collection
    And I click the contextual link "Unpin" in the "Fast lightning" tile
    Then the "Fast lightning" tile should not be marked as pinned
    When I go to the homepage of the "Orange Wrench" collection
    Then the "Fast lightning" tile should be marked as pinned
