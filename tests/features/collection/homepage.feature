@api @group-a
Feature: Collection homepage
  In order find content around a topic
  As a user of the website
  I need to be able see all content related to a collection on the collection homepage

  Background:
    Given users:
      | Username | Status | Roles     |
      | Frodo    | active |           |
      | Boromir  | active |           |
      | Legolas  | active |           |
      | Gimli    | active |           |
      | Samwise  | active | moderator |
    And owner content:
      | name          |
      | Bilbo Baggins |
    And collection content:
      | title              | description        | owner         | contact information | logo     | group_moderation | content_creation | topic                            | published at      | modification date |
      | Middle earth daily | Middle earth daily | Bilbo Baggins | Contact name        | logo.png | yes              | members          | Employment and Support Allowance | 2012-12-01 9:00am | 2017-12-01 9:00am |
    And solution content:
      | title        | collection         | description           | content_creation | creation date     | topic |
      | Bilbo's book | Middle earth daily | Bilbo's autobiography | registered users | 2014-10-17 8:32am | Info  |
    And the following collection user memberships:
      | collection         | user    | roles       |
      | Middle earth daily | Frodo   | facilitator |
      | Middle earth daily | Boromir |             |
      | Middle earth daily | Legolas |             |
    And news content:
      | title                                             | body                | topic             | collection         | created           | changed  |
      | Rohirrim make extraordinary deal                  | Horse prices drops  | Finance in EU     | Middle earth daily | 2014-10-17 8:34am | 2017-7-5 |
      | Breaking: Gandalf supposedly plans his retirement | A new white wizard? | Supplier exchange | Middle earth daily | 2014-10-17 8:31am | 2017-7-5 |
    And event content:
      | title                                    | short title      | field_paragraphs_body                     | collection         | created           | start date          | end date            | topic             | changed  |
      | Big hobbit feast - fireworks at midnight | Big hobbit feast | Barbecue followed by dance and fireworks. | Middle earth daily | 2014-10-17 8:33am | 2016-03-15T11:12:12 | 2016-03-15T11:12:12 | Supplier exchange | 2017-7-5 |

  Scenario: The collection homepage shows the collection metrics.
    When I go to the homepage of the "Middle earth daily" collection
    Then I see the text "3 Members" in the "Header" region
    And I see the text "1 Solution" in the "Header" region

    # Test caching of the metrics: Solutions.
    When I delete the "Bilbo's book" solution
    And I reload the page
    Then I see the text "3 Members" in the "Header" region
    And I see the text "0 Solutions" in the "Header" region

    When I delete the "Frodo" user
    And I reload the page
    Then I see the text "2 Members" in the "Header" region
    And I see the text "0 Solutions" in the "Header" region

  Scenario: The collection homepage is cached for anonymous users
    Given I am an anonymous user
    And I go to the homepage of the "Middle earth daily" collection
    Then the page should be cacheable
    When I reload the page
    Then the page should be cached

  Scenario Outline: The collection homepage is cached for authenticated users
    Given I am logged in as <user>
    And I go to the homepage of the "Middle earth daily" collection
    Then the page should be cacheable
    When I reload the page
    Then the page should be cached

    Examples:
      | user    |
      | Frodo   |
      | Boromir |
      | Gimli   |
      | Samwise |

  Scenario: The collection homepage shows related content.
    When I go to the homepage of the "Middle earth daily" collection
    # The collection content is shown here in the correct order.
    Then I should see the following tiles in the correct order:
      | Rohirrim make extraordinary deal                  |
      | Big hobbit feast - fireworks at midnight          |
      | Bilbo's book                                      |
      | Breaking: Gandalf supposedly plans his retirement |

    # Test that unrelated content does not show up in the tiles.
    And I should not see the "Bilbo Baggins" tile
    # Test that the collection itself does not show up in the tiles.
    And I should not see the "Middle earth daily" tile

    # Test the filtering by facets.
    When I click the Event content tab
    Then I should see the "Big hobbit feast - fireworks at midnight" tile
    But I should not see text matching "Rohirrim make extraordinary deal"
    And I should not see text matching "Breaking: Gandalf supposedly plans his retirement"
    When I click the News content tab
    Then I should not see text matching "Big hobbit feast - fireworks at midnight"
    But I should see the "Rohirrim make extraordinary deal" tile
    And I should see the "Breaking: Gandalf supposedly plans his retirement" tile

    # Deselect the content type filter.
    When I click the News content tab
    # Verify the topic inline facet.
    Then "all topics" should be selected in the "collection topic" inline facet
    And the "collection topic" inline facet should allow selecting the following values:
      | eGov (2)                |
      | Supplier exchange (2)   |
      | Economy and Welfare (1) |
      | Finance in EU (1)       |
      | Info (1)                |

    When I click "Supplier exchange" in the "collection topic" inline facet
    Then "Supplier exchange (2)" should be selected in the "collection topic" inline facet
    And the "collection topic" inline facet should allow selecting the following values:
      | eGov (2)                |
      | Economy and Welfare (1) |
      | Finance in EU (1)       |
      | Info (1)                |
      | all topics              |
    Then I should see the following tiles in the correct order:
      | Big hobbit feast - fireworks at midnight          |
      | Breaking: Gandalf supposedly plans his retirement |
    But I should not see the "Rohirrim make extraordinary deal" tile

    # Verify that the inline widget reset link doesn't break other active facets.
    When I click the News content tab
    Then "Supplier exchange (1)" should be selected in the "collection topic" inline facet
    And the "collection topic" inline facet should allow selecting the following values:
      | Economy and Welfare (1) |
      | eGov (1)                |
      | Finance in EU (1)       |
      | all topics              |
    And I should see the "Breaking: Gandalf supposedly plans his retirement" tile
    But I should not see the "Big hobbit feast - fireworks at midnight" tile
    And I should not see the "Rohirrim make extraordinary deal" tile
    # Reset the topic selection.
    When I click "all topics" in the "collection topic" inline facet
    Then "all topics" should be selected in the "collection topic" inline facet
    And the "collection topic" inline facet should allow selecting the following values:
      | Economy and Welfare (1) |
      | eGov (1)                |
      | Finance in EU (1)       |
      | Supplier exchange (1)   |
    And I should see the "Breaking: Gandalf supposedly plans his retirement" tile
    And I should see the "Rohirrim make extraordinary deal" tile
    But I should not see the "Big hobbit feast - fireworks at midnight" tile

  Scenario: The collection homepage shows details.
    When I go to the homepage of the "Middle earth daily" collection

    Then I should see the following headings:
      | Detailed information |
      | Moderation           |
    And I should see the following lines of text:
      | Owner              |
      | Bilbo Baggins      |
      | Middle earth daily |
      | Published on       |
      | 01/12/2012         |
      | Last updated       |
      | Open collection    |
      | Moderated          |

  Scenario: The collection homepage should be cacheable for anonymous users.
    Given I am an anonymous user
    When I go to the homepage of the "Middle earth daily" collection
    Then the page should not be cached
    When I reload the page
    Then the page should be cached
