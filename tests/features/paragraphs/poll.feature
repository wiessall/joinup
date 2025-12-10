@api @javascript @group-h
Feature: Pool paragraph tests.

  Background:
    Given collection content:
      | title           | state     |
      | Poll collection | published |

  Scenario: Anonymous user is asked to log in if they attempt to vote
    Given users:
      | Username   | E-mail           |
      | Cecil Poll | cpoll@example.eu |
    Given CAS users:
      | Username | E-mail              | Password   | First name | Last name | Local username |
      | cpoll    | cpoll@ec.example.eu | poll123!#$ | Cecil      | Poll      | Cecil Poll     |
    And the following polls:
      | question                  | runtime | cancel_vote_allow | show_number_of_results | choice                                                     | state     |
      | What was the best series? | 1209600 | 0                 | 1                      | Game of Thrones,Breaking Bad,The Wire,The Sopranos,Mad Men | published |
    And custom_page content:
      | title     | body                                                         | collection      | state     |
      | TV series | We've rounded up our picks for the best shows to binge-watch | Poll collection | published |
    And I append poll "What was the best series?" to "field_paragraphs_body" field in "node" entity with following "TV series" title

    Then I am an anonymous user
    And I go to the "TV series" custom_page
    When I should see the link "Game of Thrones"

    Then I click "Game of Thrones"
    When a modal should open
    And I should see the text "You are not signed in" in the "Modal title"
    And I should see the heading "Only signed in users can vote." in the "Modal content"
    And I should see the text "Please sign in if you already have an account or register a new account on EU Login to proceed voting!" in the "Modal content"

    Then I click "Sign in" in the "Modal content"
    When I should see the heading "Sign in to continue"
    Then I fill in the following:
      | E-mail address | cpoll@ec.example.eu |
      | Password       | poll123!#$          |
    And I press "Log in"
    And I see h1 span element with title "TV series"

  Scenario: Authenticated user can vote
    Given the following polls:
      | question                  | runtime | cancel_vote_allow | show_number_of_results | result_vote_allow | choice                                                     | state     |
      | What was the best series? | 1209599 | 0                 | 1                      | 1                 | Game of Thrones,Breaking Bad,The Wire,The Sopranos,Mad Men | published |
    And custom_page content:
      | title     | body                                                         | collection      | state     |
      | TV series | We've rounded up our picks for the best shows to binge-watch | Poll collection | published |
    And I append poll "What was the best series?" to "field_paragraphs_body" field in "node" entity with following "TV series" title

    Then I am logged in as a user with the "authenticated" role
    And I go to the "TV series" custom_page
    When I should see the heading "What was the best series?"
    And I should see the button "View results"
    And the following fields should be present "Game of Thrones,Breaking Bad,The Wire,The Sopranos,Mad Men"
    And I should see "0 votes"
    And I should see "1 week 6 days left to vote"

    Then I press "View results"
    When I should not see the button "View results"
    Then I press "Hide results"
    When I should not see the button "Hide results"

    Then I click on label "Game of Thrones"
    When I should see "1 vote"

  Scenario: Use who voted can cancel own vote
    Given the following polls:
      | question                      | runtime | cancel_vote_allow | show_number_of_results | choice                                    | state     |
      | Whats the best pizza topping? | 431999  | 1                 | 0                      | Pepperoni,Pineapple,Just cheese,Mushrooms | published |
    And custom_page content:
      | title       | body                                              | collection      | state     |
      | Pizza types | 10 Most Popular Types of Pizza Around the Country | Poll collection | published |
    And I append poll "Whats the best pizza topping?" to "field_paragraphs_body" field in "node" entity with following "Pizza types" title

    Then I am logged in as a user with the "authenticated" role
    And I go to the "Pizza types" custom_page
    When I should see the heading "Whats the best pizza topping?"
    And I should not see the button "View results"
    And the following fields should be present "Pepperoni,Pineapple,Just cheese,Mushrooms"
    And I should not see "0 votes"
    And I should see "4 days 23 hours left to vote"

    Then I click on label "Just cheese"
    When the following fields should not be present "Pepperoni,Pineapple,Just cheese,Mushrooms"
    And I press "Cancel vote"
    And the following fields should be present "Pepperoni,Pineapple,Just cheese,Mushrooms"

  Scenario: Poll paragraph can be added
    Given custom_page content:
      | title           | collection      | state     | abstract                 |
      | Scariest things | Poll collection | published | Scariest things abstract |
    And I append "layout" to "field_paragraphs_body" field in "node" entity with following "Scariest things" title:
      | behavior_settings | a:3:{s:17:"layout_paragraphs";a:4:{s:6:"layout";s:13:"layout_onecol";s:6:"config";a:1:{s:5:"label";s:0:"";}s:11:"parent_uuid";N;s:6:"region";N;}s:16:"background_theme";a:0:{}s:11:"extra_class";a:0:{}} |

    Then I am logged in as a moderator
    And I go to the edit form of the "Scariest things" "custom page"
    And I click "Choose component"
    And a modal should open
    And I click "Poll"
    When the following fields should be present "Question,Choice,Poll Duration,Allow cancel votes,Show number of results,Active"

    Then I press "Save" in the "Modal buttons" region
    And I should see the following inline form error messages:
      | Error messages                 |
      | Question field is required.    |
      | This value should not be null. |

    Then I fill in "Question" with "What do you find scariest?"
    And I fill in "Choice" with "Heights"
    And I press "Add another item" in the "Modal content" region
    And I fill in the latest "Choice" field with "Flying"
    And I press "Add another item" in the "Modal content" region
    And I fill in the latest "Choice" field with "Small spaces"
    And I press "Add another item" in the "Modal content" region
    And I fill in the latest "Choice" field with "Work"
    And I press "Save" in the "Modal buttons" region
    And I press "Update"
    When the following fields should be present "Heights,Flying,Small spaces,Work"
