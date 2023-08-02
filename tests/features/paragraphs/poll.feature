@api @group_b
Feature: Pool paragraph tests.

  Background:
    Given the following collections:
      | title           | state     |
      | Poll collection | validated |

  @javascript
  Scenario: Anonymous user is asked to log in if they attempt to vote
    Given the following polls:
      | question                  | runtime | cancel_vote_allow | show_number_of_results | choice                                                     | state     |
      | What was the best series? | 1209600 | 0                 | 1                      | Game of Thrones,Breaking Bad,The Wire,The Sopranos,Mad Men | published |
    And custom_page content:
      | title     | body                                                         | collection      | state     |
      | TV series | We've rounded up our picks for the best shows to binge-watch | Poll collection | published |
    And I append poll "What was the best series?" to "field_paragraphs_body" field in "node" entity with following "TV series" title

    Then I am an anonymous user
    And I go to the "TV series" custom page
    When I should see the link "Game of Thrones"

    Then I click "Game of Thrones"
    When a modal should open
    And I should see the text "You are not logged in" in the "Modal title"
    And I should see the heading "Only logged in users can vote." in the "Modal content"
    And I should see the text "Please log in if you already have an account or register a new account on EU Login to proceed voting!" in the "Modal content"

    Then I click "Log in" in the "Modal content"
    When I should see the heading "Sign in to continue"

  @javascript
  Scenario: Authenticated user can vote
    Given the following polls:
      | question                  | runtime | cancel_vote_allow | show_number_of_results | choice                                                     | state     |
      | What was the best series? | 1209600 | 0                 | 1                      | Game of Thrones,Breaking Bad,The Wire,The Sopranos,Mad Men | published |
    And custom_page content:
      | title     | body                                                         | collection      | state     |
      | TV series | We've rounded up our picks for the best shows to binge-watch | Poll collection | published |
    And I append poll "What was the best series?" to "field_paragraphs_body" field in "node" entity with following "TV series" title

    Then I am logged in as a user with the "authenticated" role
    And I go to the "TV series" custom page
    When I should see the heading "What was the best series?"
    And I should not see the button "View results"
    And the following fields should be present "Game of Thrones,Breaking Bad,The Wire,The Sopranos,Mad Men"
    And I should see "0 votes"
    And I should see "1 week 6 days left to vote"

    Then I select the radio button "Game of Thrones"
    And I wait for AJAX to finish
    When I should see "1 vote"

  @javascript
  Scenario: Use who voted can cancel own vote
    Given the following polls:
      | question                      | runtime | cancel_vote_allow | show_number_of_results | choice                                    | state     |
      | Whats the best pizza topping? | 432000  | 1                 | 0                      | Pepperoni,Pineapple,Just cheese,Mushrooms | published |
    And custom_page content:
      | title       | body                                              | collection      | state     |
      | Pizza types | 10 Most Popular Types of Pizza Around the Country | Poll collection | published |
    And I append poll "Whats the best pizza topping?" to "field_paragraphs_body" field in "node" entity with following "Pizza types" title

    Then I am logged in as a user with the "authenticated" role
    And I go to the "Pizza types" custom page
    When I should see the heading "Whats the best pizza topping?"
    And the following fields should be present "Pepperoni,Pineapple,Just cheese,Mushrooms"
    And I should not see "0 votes"
    And I should see "4 days 23 hours left to vote"

    Then I select the radio button "Just cheese"
    And I wait for AJAX to finish
    When the following fields should not be present "Pepperoni,Pineapple,Just cheese,Mushrooms"
    And I press "Cancel vote"
    And the following fields should be present "Pepperoni,Pineapple,Just cheese,Mushrooms"

  @javascript
  Scenario: Poll paragraph can be added
    Given custom_page content:
      | title           | collection      | state     |
      | Scariest things | Poll collection | published |

    Then I am logged in as a moderator
    And I go to the edit form of the "Scariest things" "custom page"
    And I click "Choose component"
    And I click "Poll"
    When the following fields should be present "Question,Choice,Poll Duration,Allow cancel votes,Show number of results,Active"

    Then I press "Save" in the "Modal buttons" region
    When I should see the error message "Question field is required."
    And I should see the error message "This value should not be null."

    Then I fill in "Question" with "What do you find scariest?"
    And I fill in "Choice" with "Heights"
    And I press "Add another item" at the "Choice" field
    And I fill in the latest "Choice" field with "Flying"
    And I press "Add another item" at the "Choice" field
    And I fill in the latest "Choice" field with "Small spaces"
    And I press "Add another item" at the "Choice" field
    And I fill in the latest "Choice" field with "Work"
    And I press "Save" in the "Modal buttons" region
    And I press "Update"
    When the following fields should be present "Heights,Flying,Small spaces,Work"
