@api @javascript @group-h
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
      | Bulgaria | published |
    Given the following solutions:
      | title               | state     | collection |
      | Very liked solution | published | Bulgaria   |

  Scenario: Like functionality is disabled for archived content.
    Given the following solutions:
      | title         | state    | collection |
      | Mount Vitosha | archived | Bulgaria   |

    When I go to the homepage of the "Mount Vitosha" solution
    Then I should not see the link "Like" in the "Like block" region

    When I am logged in as "Gregory Like"
    And I go to the homepage of the "Mount Vitosha" solution
    Then I should not see the link "Like" in the "Like block" region

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

  Scenario: Anonymous user is asked to sign in if they attempt to Like
    Given users:
      | Username   | E-mail           |
      | Cecil Like | clike@example.eu |
    And CAS users:
      | Username | E-mail              | Password   | First name | Last name | Local username |
      | clike    | clike@ec.example.eu | like123!#$ | Cecil      | Like      | Cecil Like     |

    Given I am an anonymous user
    When I go to the homepage of the "Very liked solution" solution
    Then I should see the link "Like" in the "Like block"
    And I should see the text "Like (0)" in the "Like block" region
    When I click "Like" in the "Like block" region
    Then a modal should open
    And I should see the text "You are not signed in"
    And I should see the text "You are not signed in" in the "Modal title"
    And I should see the heading "Sign in or register to like this content" in the "Modal content"
    And I should see the text "Liking content shows your appreciation to content creators and helps other users find good content" in the "Modal content"
    When I click "Sign in" in the "Modal content"
    Then I should see the heading "Sign in to continue"
    When I fill in the following:
      | E-mail address | clike@ec.example.eu |
      | Password       | like123!#$          |
    And I press "Log in"
    Then I should see the heading "Very liked solution"

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
      | news         | published |
      | event        | published |
      | discussion   | published |
      | document     | published |
      | custom_page  | published |

  Scenario Outline: Logged-in user can see and hit like button in document, discussion, events and news tiles
    Given <content type> content:
      | title                   | collection | state     | author    |
      | Likeable <content type> | Bulgaria   | published | lisa Like |

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
      | document     | published |               |
      | event        | published |               |
      | news         | published | Likeable news |

  Scenario: Logged-in user can see and hit like button in custom page, discussion and solution tiles
    Given the following solutions:
      | title             | state     | collection |
      | Likeable solution | published | Bulgaria   |
    And "custom_page" content:
      | title                | collection | author    | state     |
      | Likeable custom page | Bulgaria   | Lisa Like | published |
    And "discussion" content:
      | title               | collection | state     |
      | Likeable discussion | Bulgaria   | published |

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

  @javascript @terms
  Scenario: Tiles in the carousel should also give possibility to like content.
    Given custom_page content:
      | title                | collection | author    | state     |
      | Likeable custom page | Bulgaria   | Lisa Like | published |
    And document content:
      | title             | collection | state     |
      | Likeable document | Bulgaria   | published |
    And discussion content:
      | title               | collection | state     |
      | Likeable discussion | Bulgaria   | published |
    And news content:
      | title         | headline      | collection | state     |
      | Likeable news | Likeable news | Bulgaria   | published |
    And event content:
      | title          | collection | state     |
      | Likeable event | Bulgaria   | published |
    And the following collection user memberships:
      | collection | user      | roles                      | state  |
      | Bulgaria   | Lisa Like | administrator, facilitator | active |

    Given I am logged in as "Lisa Like"
    And I go to the homepage of the "Bulgaria" collection

    And I open the plus button menu
    And I click "Add custom page"

    And I fill in "Title" with "Carousel of likes"
    And I select "HR" from "Topic"
    And I add a layout paragraph in the "Custom page body" form field with the following properties:
      | layout           | one column |
      | Background theme | light      |
      | Extra classes    | test-class |

    And I click "Choose component"
    And I click "Carousel"

    Then I select "Solution" from "Available filters"
    And I press "Add and configure filter"
    And I fill in "Solution" with "Very liked solution"

    Then I select "Custom page" from "Available filters"
    And I press "Add and configure filter"
    And I fill in "Custom page" with "Likeable custom page"

    Then I select "Discussion" from "Available filters"
    And I press "Add and configure filter"
    And I fill in "Discussion" with "Likeable discussion"

    Then I select "Document" from "Available filters"
    And I press "Add and configure filter"
    And I fill in "Document" with "Likeable document"

    Then I select "Event" from "Available filters"
    And I press "Add and configure filter"
    And I fill in "Event" with "Likeable event"

    Then I select "News" from "Available filters"
    And I press "Add and configure filter"
    And I fill in "News" with "Likeable news"

    # Save the paragraph through the dialog.
    And I press "Save" in the "Layout paragraphs dialog buttons" region
    When I press "Publish"

    And I should see the "Very liked solution" tile
    And I should see the link "Like this content" in the "Very liked solution" tile
    And I should see the "Likeable custom page" tile
    And I should see the link "Like this content" in the "Likeable custom page" tile
    And I should see the "Likeable discussion" tile
    And I should see the link "Like this content" in the "Likeable discussion" tile
    And I should see the "Likeable document" tile
    And I should see the link "Like this content" in the "Likeable document" tile
    Then I press "Next item"
    And I wait up to 5 seconds for CSS transitions to finish
    And I press "Next item"
    And I wait up to 5 seconds for CSS transitions to finish
    And I should see the "Likeable event" tile
    And I should see the link "Like this content" in the "Likeable event" tile
    And I should see the "Likeable news" tile
    And I should see the link "Like this content" in the "Likeable news" tile
