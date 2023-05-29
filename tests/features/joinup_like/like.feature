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
    Given the following solutions:
      | title               | state     |
      | Very liked solution | validated |

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

  Scenario: Anonymous user is asked to log in if they attempt to Like
    Given I am an anonymous user
    When I go to the homepage of the "Very liked solution" solution
    Then I should see the link "Like" in the "Like block"
    And I should see the text "Like (1)" in the "Like block" region
    When I click "Like" in the "Like block" region
    Then a modal should open
    And I should see the heading "You are not logged in"
    And I should see the text "You are not logged in" in the "Modal title"
    And I should see the heading "Login or register to like this content" in the "Modal content"
    And I should see the text "Like this content will help other people find reliable information about what they search !" in the "Modal content"
    When I click "Log in" in the "Modal content"
    Then I should see the heading "Sign in to continue"

  Scenario Outline: User can like/unlike solution's content
    Given <content type> content:
      | title                        | solution            | state     |
      | Liked <content type> content | Very liked solution | validated |

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
      | content type |
      | news         |
      | event        |
      | discussion   |
      | document     |
      | custom_page  |
