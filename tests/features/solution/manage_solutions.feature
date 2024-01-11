@api @group-g
Feature: As a moderator I want to be able to move solutions to other collection.

  Background:
    Given the following collections:
      | uri                            | title       | state     |
      | http://example.com/source      | Source      | published |
      | http://example.com/destination | Destination | published |
    And solutions:
      | title      | collection | state     | creation date    | modification date |
      | Solution 1 | Source     | published | 2017-11-18T10:00 | 2017-12-18T10:00  |
      | Solution 2 | Source     | published | 2018-11-19T10:00 | 2018-12-19T10:00  |
      | Solution 3 | Source     | published | 2019-11-20T10:00 | 2019-12-20T10:00  |
      | Solution 4 | Source     | published | 2020-11-21T10:00 | 2020-12-21T10:00  |
      | Solution 5 | Source     | archived  | 2021-11-22T10:00 | 2021-12-22T10:00  |
      | Solution 6 | Source     | archived  | 2022-11-23T10:00 | 2022-12-23T10:00  |

  Scenario: Moderators can move a solution to a different collection.
    # Only moderators should be able to access this feature.
    Given I am an anonymous user
    When I go to the homepage of the "Source" collection
    Then I should not see the link "Manage solutions"
    When I go to "/rdf_entity/http_e_f_fexample_ccom_fsource/change-collection"
    Then I should see the heading "Sign in to continue"

    Given I am logged in as an "authenticated"
    When I go to the homepage of the "Source" collection
    Then I should not see the link "Manage solutions"
    When I go to "/rdf_entity/http_e_f_fexample_ccom_fsource/change-collection"
    Then I should get an access denied error

    Given I am logged in as a moderator

    # The 'Manage solutions' link shows only on collections.
    When I go to the homepage of the "Solution 1" solution
    Then I should not see the link "Manage solutions"

    When I go to the homepage of the "Source" collection
    Then I should see the link "Manage solutions"
    When I click "Manage solutions"
    Then I should see the heading "Manage solutions"

    Then I select the "Solution 1" row
    And I select the "Solution 4" row
    And I select "Move to other collection" from "Action"

    When I press "Apply to selected items"
    Then I should see the heading "Select a destination collection"
    And I should see the following lines of text:
      | The following solutions from Source collection will be moved to a new collection: |
      | Solution 1                                                                        |
      | Solution 4                                                                        |

    # Trying to move solutions to the same collection should result in an error.
    When I fill in "Select the destination collection" with "Source"
    And I press "Move solutions"
    Then I should see the error message "The destination collection cannot be the same as the source collection."

    When I fill in "Select the destination collection" with "Destination"
    And I press "Move solutions"
    Then I should see the following success messages:
      | success messages                                   |
      | Solution Solution 1 has been moved to Destination. |
      | Solution Solution 4 has been moved to Destination. |

    When I go to the homepage of the "Destination" collection
    Then I should see the following tiles in the correct order:
      | Solution 4 |
      | Solution 1 |

    When I go to the homepage of the "Source" collection
    Then I should see the following tiles in the correct order:
      | Solution 3 |
      | Solution 2 |

  Scenario: Archiving a solution must archive its sub-content.
    And "event" content:
      | title            | solution   | state     |
      | Solution 1 Event | Solution 1 | published |
    And "news" content:
      | title           | solution   | state     |
      | Solution 1 News 1 | Solution 1 | published |
      | Solution 1 News 2 | Solution 1 | draft     |
      | Solution 1 News 3 | Solution 1 | published |
    And the workflow state of the "Solution 1 News 3" node is changed to "needs_update"
    And "document" content:
      | title               | collection | state     |
      | Solution 1 Document | Solution 1 | published |
    And "discussion" content:
      | title                 | collection | state     |
      | Solution 1 Discussion | Solution 1 | published |
    And distribution:
      | title  | Solution 1 Distribution |
      | parent | Solution 1              |
      | state  | published               |
    And releases:
      | title                | is version of | state     |
      | Solution 1 Release 1 | Solution 1    | published |
      | Solution 1 Release 2 | Solution 1    | draft     |
      | Solution 1 Release 3 | Solution 1    | published |
    And the workflow state of the "Solution 1 Release 3" "rdf_entity" is changed to "needs_update"
    Given I am logged in as a user with the moderator role and I have the following fields:
      | name                   | John The Moderator             |
      | mail                   | john.the.moderator@example.com |
      | field_user_first_name  | John                           |
      | field_user_family_name | Moderator                      |
    # Change the state through the VBO form since it does not triggers form or
    # entity validation.
    When I go to the homepage of the "Source" collection
    And I click "Manage solutions"
    And the "manage solutions" table should contain the following columns:
      | Title      | Created on         | State     |
      | Solution 1 | 18/11/2017 - 10:00 | Published |
    When I select the "Solution 1" row
    And I select "Archive solutions" from "Action"
    And I press "Apply to selected items"
    Then I should see the heading "Confirmation of Changing the State of Solutions to Archived"
    And I should see the following lines of text:
      | Please confirm the state transition of the selected solution. |
      | The action will be applied on the following items:            |
      | Solution 1                                                    |
    And I should not see the following lines of text:
      | The following items can't be changed: |
    And I should see the button "Confirm"
    When I press "Confirm"
    Then I should see the following success messages:
      | Success messages                                                            |
      | The Solution 1 solution's state was changed to Archived.                    |
      | The archiving process of the Solution has been initiated.                   |
      | You will receive an email notification as soon as the process is completed. |
    And the "manage solutions" table should contain the following columns:
      | Title      | Created on         | State    |
      | Solution 1 | 18/11/2017 - 10:00 | Archived |
    When I run cron
    Then the workflow state of the "Solution 1 Event" content should be "archived"
    And the workflow state of the "Solution 1 News 1" content should be "archived"
    And the workflow state of the "Solution 1 News 2" content should be "draft"
    And the workflow state of the "Solution 1 News 3" content should be "archived"
    And the workflow state of the "Solution 1 Document" content should be "archived"
    And the workflow state of the "Solution 1 Discussion" content should be "archived"
    And the workflow state of the "Solution 1 Distribution" "rdf entity" should be "archived"
    And the workflow state of the "Solution 1 Release 1" "rdf entity" should be "archived"
    And the workflow state of the "Solution 1 Release 2" "rdf entity" should be "draft"
    And the workflow state of the "Solution 1 Release 3" "rdf entity" should be "archived"
