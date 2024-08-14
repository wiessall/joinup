@api @group-i
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

  Scenario Outline: Manage solutions page must have a bulk-archive and bulk-unarchive option.
    Given I am logged in as a moderator
    When I go to the homepage of the "Source" collection
    Then I should see the heading "Source"
    And I should see the link "Manage solutions"
    And I click "Manage solutions"
    Then I should see the heading "Manage solutions"
    Then the "manage solutions" table should contain the following columns:
      | Title      | Created on         | Last update        | State     |
      | Solution 6 | 23/11/2022 - 10:00 | 23/12/2022 - 10:00 | Archived  |
      | Solution 5 | 22/11/2021 - 10:00 | 22/12/2021 - 10:00 | Archived  |
      | Solution 4 | 21/11/2020 - 10:00 | 21/12/2020 - 10:00 | Published |
      | Solution 3 | 20/11/2019 - 10:00 | 20/12/2019 - 10:00 | Published |
      | Solution 2 | 19/11/2018 - 10:00 | 19/12/2018 - 10:00 | Published |
      | Solution 1 | 18/11/2017 - 10:00 | 18/12/2017 - 10:00 | Published |
    When I select the "Solution 3" row
    And I select the "Solution 4" row
    And I select the "Solution 5" row
    And I select the "Solution 6" row
    And I select "<option>" from "Action"
    And I press "Apply to selected items"
    Then I should see the heading "Confirmation of Changing the State of Solutions to <target state>"
    And I should see the button "Confirm"
    When I press "Confirm"
    # Two solution were changed.
    Then I should see the following success messages:
      | Success messages                         |
      | The following solutions' state was changed to <target state>: <changed solutions>. |
    And the "manage solutions" table should contain the following columns:
      # Skip last update since it is not under our control.
      | Title      | Created on         | State          |
      | Solution 6 | 23/11/2022 - 10:00 | <target state> |
      | Solution 5 | 22/11/2021 - 10:00 | <target state> |
      | Solution 4 | 21/11/2020 - 10:00 | <target state> |
      | Solution 3 | 20/11/2019 - 10:00 | <target state> |
      | Solution 2 | 19/11/2018 - 10:00 | Published      |
      | Solution 1 | 18/11/2017 - 10:00 | Published      |

    Examples:
      | option                        | target state | changed solutions      |
      | Archive solutions             | Archived     | Solution 3, Solution 4 |
      | Unarchive (publish) solutions | Published    | Solution 5, Solution 6 |
