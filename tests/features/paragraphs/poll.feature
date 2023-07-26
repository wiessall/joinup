@api @group_b
Feature: Pool paragraph tests.

  Background:
    Given the following collections:
      | title                  | state     |
      | Popular poll questions | validated |
    And the following polls:
      | question                      | runtime | cancel_vote_allow | show_number_of_results | choice                                                     | state     |
      | Whats the best pizza topping? | 86400   | 0                 | 1                      | Pepperoni,Pineapple,Just cheese,Mushrooms                  | published |
      | What do you find scariest?    | 259200  | 1                 | 0                      | Heights,Flying,Small spaces,Work,Needles                   | published |
      | What was the best series?     | 1209600 | 0                 | 1                      | Game of Thrones,Breaking Bad,The Wire,The Sopranos,Mad Men | published |
    And custom_page content:
      | title        | body                                             | collection             | state     |
      | Poll example | Popular poll questions with examples and samples | Popular poll questions | published |

  Scenario: Anonymous user is asked to log in if they attempt to vote
    Given I am an anonymous user
    When I go to the "Poll example" custom page
