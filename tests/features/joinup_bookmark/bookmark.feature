@api @javascript @group-a
Feature: Bookmark/Unbookmark feature.
  In order to express appreciation or approval of a content item
  As a user
  I need to be able to Bookmark or Unbookmark content.

  Background:
    Given users:
      | Username         | E-mail               | First name | Family name |
      | Lisa Bookmark    | lisa@bookmark.com    | Lisa       | Bookmark    |
      | Gregory Bookmark | gregory@bookmark.com | Gregory    | Bookmark    |
    Given the following collections:
      | title  | state     |
      | Batman | published |
    Given the following solutions:
      | title         | state     | collection |
      | Batman Begins | published | Batman     |

  Scenario Outline: User can Bookmark/unbookmark rdf.
    When I am logged in as "Gregory Bookmark"
    And I go to the homepage of the "<name>" <type>
    And I click "Bookmark" in the "Bookmark block" region
    Then I should see the link "Unbookmark" in the "Bookmark block"

    When I am logged in as "Lisa Bookmark"
    And I go to the homepage of the "<name>" <type>
    Then I should see the link "Bookmark" in the "Bookmark block"

    When I am logged in as "Gregory Bookmark"
    And I go to the homepage of the "<name>" <type>
    And I click "Unbookmark" in the "Bookmark block" region
    Then I should see the link "Bookmark" in the "Bookmark block"

    Examples:
      | type       | name          |
      | collection | Batman        |
      | solution   | Batman Begins |

  Scenario Outline: User can bookmark content.
    And <content type> content:
      | title            | solution      | state     |
      | Bookmark content | Batman Begins | published |

    When I am logged in as "Gregory Bookmark"
    And I go to the content page of the type "<content type>" with the title "Bookmark content"
    Then I should see the link "Bookmark" in the "Bookmark block"

    Examples:
      | content type |
      | custom_page  |
      | discussion   |
      | document     |
      | event        |
      | news         |

  Scenario: User bookmark page.
    And news content:
      | title               | solution      | state     |
      | Bookmark news one   | Batman Begins | published |
      | Bookmark news two   | Batman Begins | published |
      | Bookmark news three | Batman Begins | published |
      | Ohter news          | Batman Begins | published |

    When I am logged in as "Gregory Bookmark"
    And I visit "/user/bookmarks"
    Then I should see the text "There are no bookmarked items."
    And I go to the content page of the type "news" with the title "Bookmark news one"
    And I click "Bookmark" in the "Bookmark block" region
    Then I go to the content page of the type "news" with the title "Bookmark news two"
    And I click "Bookmark" in the "Bookmark block" region
    Then I go to the homepage of the "Batman Begins" solution
    And I click "Bookmark" in the "Bookmark block" region

    When I am logged in as "Lisa Bookmark"
    And I go to the content page of the type "news" with the title "Bookmark news three"
    And I click "Bookmark" in the "Bookmark block" region
    Then I go to the content page of the type "news" with the title "Ohter news"
    And I click "Bookmark" in the "Bookmark block" region
    Then I go to the homepage of the "Batman" collection
    And I click "Bookmark" in the "Bookmark block" region

    When I am logged in as "Gregory Bookmark"
    And I visit "/user/bookmarks"
    Then I should not see the text "There are no bookmarked items."
    And I should see the following tiles in the correct order:
      | Batman Begins     |
      | Bookmark news two |
      | Bookmark news one |
    And the "Entity bundle" tabs facet should allow selecting the following values:
      | 2 News      |
      | 1 Solutions |

    When I am logged in as "Lisa Bookmark"
    And I visit "/user/bookmarks"
    Then I should see the following tiles in the correct order:
      | Batman              |
      | Ohter news          |
      | Bookmark news three |
    And the "Entity bundle" tabs facet should allow selecting the following values:
      | 2 News        |
      | 1 Collections |
