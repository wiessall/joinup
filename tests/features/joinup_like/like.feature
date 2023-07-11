@api @group-a @javascript
Feature: Like/unlike feature.
  In order to express appreciation or approval of a content item
  As a user
  I need to be able to like or unlike content.

  Background:
    Given users:
      | Username     | Roles | E-mail           | First name | Family name |
      | Lisa Like    |       | lisa@like.com    | Lisa       | Like        |
      | Gregory Like |       | gregory@like.com | Gregory    | Like        |
    Given the following collections:
      | title    | state     |
      | Bulgaria | validated |
    Given the following solutions:
      | title               | state     | collection |
      | Very liked solution | validated | Bulgaria   |

  Scenario: User can like/unlike solution
    Given I am logged in as "Gregory Like"
    When I go to the homepage of the "Very liked solution" solution
    And I click "Like" in the "Like block" region
    Then I should see the link "Unlike" in the "Like block"
    And I see the text "Like (1)" in the "Like block" region

    Given I am logged in as "Lisa Like"
    When I go to the homepage of the "Very liked solution" solution
    And I click "Like" in the "Like block" region
    Then I should see the link "Unlike" in the "Like block"
    And I see the text "Like (2)" in the "Like block" region

    When I click "Unlike" in the "Like block" region
    Then I should see the link "Like" in the "Like block"
    And I see the text "Like (1)" in the "Like block" region

    When I go to the homepage of the "Bulgaria" collection
    Then I should see the "Very liked solution" tile
    And I should see the statistics "1" in the "Very liked solution" tile
    When I click the "Like this content" in the "Very liked solution" tile
    Then I should see the statistics "2" in the "Very liked solution" tile

    When I visit the search page
    Then I should see the "Very liked solution" tile
    And I should see the statistics "2" in the "Very liked solution" tile
    When I click the "Remove like from this content" in the "Very liked solution" tile
    Then I should see the statistics "1" in the "Very liked solution" tile

  Scenario: Anonymous user is asked to log in if they attempt to Like
    Given I am an anonymous user
    When I go to the homepage of the "Very liked solution" solution
    Then I should see the link "Like" in the "Like block"
    And I should see the text "Like (0)" in the "Like block" region
    When I click "Like" in the "Like block" region
    Then a modal should open
    And I should see the heading "You are not logged in"
    And I should see the text "You are not logged in" in the "Modal title"
    And I should see the heading "Login or register to like this content" in the "Modal content"
    And I should see the text "Like this content will help other people find reliable information about what they search!" in the "Modal content"
    When I click "Log in" in the "Modal content"
    Then I should see the heading "Sign in to continue"

  Scenario Outline: User can like/unlike solution's content
    Given <content type> content:
      | title                        | solution            | state   |
      | Liked <content type> content | Very liked solution | <state> |

    When I am logged in as "Gregory Like"
    And I go to the content page of the type "<content type>" with the title "Liked <content type> content"
    Then I should see the link "Like" in the "Like block"

    When I click "Like" in the "Like block" region
    Then I should see the link "Unlike" in the "Like block"
    And I see the text "Like (1)" in the "Like block" region

    When I click "Unlike" in the "Like block" region
    Then I should see the link "Like" in the "Like block"
    And I see the text "Like (0)" in the "Like block" region

    Examples:
      | content type | state     |
      | news         | validated |
      | event        | validated |
      | discussion   | validated |
      | document     | validated |
      | custom_page  | published |

  Scenario Outline: Logged-in user can see and hit like button in document, discussion, events and news tiles
    Given <content type> content:
      | title                   | collection | state     | author    |
      | Likeable <content type> | Bulgaria   | validated | lisa Like |

    Given I am logged in as "Gregory Like"

    # View mode tile (default)
    When I go to the homepage of the "Bulgaria" collection
    Then I should see the "Likeable <content type>" tile
    And I should see the statistics "0" in the "Likeable <content type>" tile
    When I click the "Like this content" in the "Likeable <content type>" tile
    Then I should see the statistics "1" in the "Likeable <content type>" tile

    # View mode tile horizontal
    When I visit the search page
    Then I should see the "Likeable <content type>" tile
    And I should see the statistics "1" in the "Likeable <content type>" tile
    When I click the "Remove like from this content" in the "Likeable <content type>" tile
    Then I should see the statistics "0" in the "Likeable <content type>" tile

    Examples:
      | content type | state     | headline      |
      | document     | validated |               |
      | event        | validated |               |
      | news         | validated | Likeable news |

  Scenario: Logged-in user can see and hit like button in custom page, discussion and solution tiles
    Given the following solutions:
      | title             | state     | collection |
      | Likeable solution | validated | Bulgaria   |
    And "custom_page" content:
      | title                | collection | author    |
      | Likeable custom page | Bulgaria   | Lisa Like |
    And "discussion" content:
      | title               | collection | state     |
      | Likeable discussion | Bulgaria   | validated |

    When I am logged in as "Gregory Like"
    And I go to the homepage of the "Bulgaria" collection

    # Solution vertical tile (default)
    Then I should see the "Likeable solution" tile
    And I should see the statistics "0" in the "Likeable solution" tile
    When I click the "Like this content" in the "Likeable solution" tile
    Then I should see the statistics "1" in the "Likeable solution" tile

    Then I should see the link "Discussions" in the "Navigation menu"
    When I click "Discussions"

    # Discussion vertical tile (default)
    Then I should see the "Likeable discussion" tile
    And I should see the statistics "0" in the "Likeable discussion" tile
    When I click the "Like this content" in the "Likeable discussion" tile
    Then I should see the statistics "1" in the "Likeable discussion" tile

    When I visit the search page
    And I click "Lisa Like"

    # Custom page vertical tile (default)
    Then I should see the "Likeable custom page" tile
    And I should see the statistics "0" in the "Likeable custom page" tile
    When I click the "Like this content" in the "Likeable custom page" tile
    Then I should see the statistics "1" in the "Likeable custom page" tile

    Then I visit the search page

    # Solution horizontal tile
    And I should see the "Likeable solution" tile
    And I should see the statistics "1" in the "Likeable solution" tile
    When I click the "Remove like from this content" in the "Likeable solution" tile
    Then I should see the statistics "0" in the "Likeable solution" tile

    # Discussion horizontal tile
    And I should see the "Likeable discussion" tile
    And I should see the statistics "1" in the "Likeable discussion" tile
    When I click the "Remove like from this content" in the "Likeable discussion" tile
    Then I should see the statistics "0" in the "Likeable discussion" tile

    # Custom page horizontal tile
    And I should see the "Likeable custom page" tile
    And I should see the statistics "1" in the "Likeable custom page" tile
    When I click the "Remove like from this content" in the "Likeable custom page" tile
    Then I should see the statistics "0" in the "Likeable custom page" tile
    And I should see the "Lisa Like" tile
