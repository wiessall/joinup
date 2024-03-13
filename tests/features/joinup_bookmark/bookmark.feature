@api @javascript @group-a
Feature: Bookmark/Unbookmark feature.
  In order to express appreciation or approval of a content item
  As a user
  I need to be able to Bookmark or Unbookmark content.

  Background:
    Given users:
      | Username      | E-mail            | First name | Family name |
      | Lisa Bookmark | lisa@bookmark.com | Lisa       | Bookmark    |
    And CAS users:
      | Gregory Bookmark | gregory@bookmark.com | bookmark123!#$g | Gregory | Bookmark |
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

  Scenario: Anonymous user is asked to sign in if they attempt to bookmark
    Given I am an anonymous user

    When I go to the homepage of the "Batman Begins" solution
    And I click "Bookmark" in the "Bookmark block" region
    Then a modal should open
    And I should see the text "You are not signed in" in the "Modal title"
    And I should see the heading "Sign in or register to bookmark this content" in the "Modal content"
    And I should see the text "Bookmarking content allows you to save it for later reference and helps you organise your favourite resources" in the "Modal content"

    When I click "Sign in" in the "Modal content"
    Then I should see the heading "Sign in to continue"

    When I fill in the following:
      | E-mail address | gregory@bookmark.com |
      | Password       | bookmark123!#$g      |
    And I press "Log in"
    Then I should see the heading "Batman Begins"
