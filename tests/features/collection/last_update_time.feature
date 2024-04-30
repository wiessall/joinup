@api @group-c
Feature: Tests the collection last update time.

  Scenario: As a user visiting a collection, I want to see the collection last
  update time as the maximum changed time of the collection itself, its
  affiliate solutions, community content and custom pages.

    Given users:
      | Username    | Roles     |
      | The Cleaner | moderator |
    And  the following contact:
      | email | foo@bar.com           |
      | name  | Your Cleaning Company |
    And the following owner:
      | name | type    |
      | Sean | Company |
    And the following collection:
      | title             | Household Wizard |
      | state             | published        |
      | modification date | 2011-05-06T21:56 |
      | creation date     | 2001-01-03       |
      | author            | The Cleaner      |
    And the following solutions:
      | title           | collection       | state     | modification date | creation date | author      | contact information   | owner | description                    |
      | Roof Hole Cover | Household Wizard | published | 2010-07-05T23:03  | 2001-01-01    | The Cleaner | Your Cleaning Company | Sean  | Keep your roof in a good shape |
      | Mosquito Killer | Household Wizard | proposed  | 2017-05-03T11:45  | 2001-01-02    | The Cleaner | Your Cleaning Company | Sean  | Quiet nights                   |
    And I am logged in as "The Cleaner"

    Given I go to the homepage of the "Household Wizard" collection
    # The newest is 'Mosquito Killer' solution but because is not published, it
    # doesn't count. The winner is 'Household Wizard' collection because is
    # newer than 'Roof Hole Cover' solution.
    Then the response should contain "2011-05-06T21:56"

    Given document content:
      | title           | document type | state     | changed          | created    | collection       | body               |
      | Get Rid of Rats | Document      | published | 2012-01-17T07:38 | 2001-01-04 | Household Wizard | Rats everywhere... |

    And I reload the page
    # The document is the newest thus will give the collection updated time.
    Then the response should contain "2012-01-17T07:38"

    Given discussion content:
      | title           | state     | changed          | created    | collection       |
      | Household Forum | published | 2013-04-19T15:18 | 2001-01-05 | Household Wizard |

    And I reload the page
    # The discussion is the newest thus will give the collection updated time.
    Then the response should contain "2013-04-19T15:18"

    Given event content:
      | title        | state     | changed          | created    | collection       |
      | Cleaning Day | published | 2014-06-06T22:46 | 2001-01-06 | Household Wizard |

    And I reload the page
    # The event is the newest thus will give the collection updated time.
    Then the response should contain "2014-06-06T22:46"

    Given news content:
      | title                 | state | changed          | created    | collection       |
      | The New Grass Trimmer | draft | 2015-11-08T01:05 | 2001-01-07 | Household Wizard |

    And I reload the page
    # The event is the newest but is not published, so it doesn't influence the
    # collection updated time.
    Then the response should not contain "2015-11-08T01:05"
    But the response should contain "2014-06-06T22:46"

    Given custom_page content:
      | title          | changed          | created    | collection       | state     |
      | The Kids Space | 2016-05-06T05:29 | 2001-01-07 | Household Wizard | published |

    And I reload the page
    # The custom page is the newest thus will give the collection updated time.
    Then the response should contain "2016-05-06T05:29"

    When I update the "Get Rid of Rats" document at "2016-08-28T15:01"
    And I reload the page
    Then the response should not contain "2016-05-06T05:29"
    But the response should contain "2016-08-28T15:01"

    # Editing the solution changes the last updated time.
    When I update the "Roof Hole Cover" solution at "2023-07-14T00:03"
    And I reload the page
    Then the response should not contain "2016-08-28T15:01"
    But the response should contain "2023-07-14T00:03"

    # Delete a community content node.
    Given I go to the delete form of the "Get Rid of Rats" document
    And I click "Delete"
    And I press "Delete"
    Then the "Household Wizard" collection "Last updated" time has just been updated

    # Deleting the solution changes the last updated time.
    Given I delete the "Roof Hole Cover" solution
    Then the "Household Wizard" collection "Last updated" time has just been updated
