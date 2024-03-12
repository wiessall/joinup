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
    Then I should see the link "Bookmarked" in the "Bookmark block"

    When I am logged in as "Lisa Bookmark"
    And I go to the homepage of the "<name>" <type>
    Then I should see the link "Bookmark" in the "Bookmark block"

    When I am logged in as "Gregory Bookmark"
    And I go to the homepage of the "<name>" <type>
    And I click "Bookmarked" in the "Bookmark block" region
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
