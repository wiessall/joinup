@api @group-g
Feature: Solution homepage
  In order get an idea of what a solution is about
  As a user of the website
  I need to be able see an introduction of the solution on its homepage

  Scenario: The solution homepage shows basic information about the solution
    Given owner content:
      | name          |
      | Awesome owner |
    And contact_information content:
      | name            | email                      | website url        |
      | Awesome contact | awesomecontact@example.com | http://example.com |
    Given the following solution:
      | title               | Petri net                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            |
      | description         | "<p>A <b>Petri net</b>, also known as a <b>place/transition (PT) net</b>, is one of several <a href=\"#mathematical\">mathematical</a> modeling languages for the description of distributed systems. It is a class of discrete event dynamic system. A Petri net is a directed bipartite graph, in which the nodes represent transitions (i.e. events that may occur, represented by bars) and places (i.e. conditions, represented by circles). The directed arcs describe which places are pre- and/or postconditions for which transitions (signified by arrows). Some sources state that Petri nets were invented in August 1939 by Carl Adam Petri - at the age of 13 - for the purpose of describing chemical processes.</p>" |
      | state               | published                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            |
      | owner               | Awesome owner                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        |
      | contact information | Awesome contact                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      |
      | topic               | Demography, E-inclusion                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              |
      | solution type       | Non-binding Instrument, Logging Service                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              |
      | spatial coverage    | Greece, Belgium                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      |
      | language            | English                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              |
      | status              | Completed                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            |
      | spatial coverage    | Belgium                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              |
      | creation date       | 2017-12-01T8:43                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      |
      | modification date   | 2012-12-01T8:43                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      |
      | abstract            | Short abstract description                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           |
    And release content:
      | title          | release notes    | documentation | release number | release notes   | is version of | state     | creation date    |
      | Petri net v1.1 | A sample release | text.pdf      | 1.1            | Changed release | Petri net     | published | 2022-12-21T13:00 |

    When I go to the homepage of the "Petri net" solution
    Then the page should be cacheable
    And I should see the following headings:
      | Quick links          |
      | Detailed information |
      | Moderation           |
    And I should see the following lines of text:
      | Short abstract description                        |
      | Last update                                       |
      | 21/12/2022                                        |
      | Owner                                             |
      | Awesome owner                                     |
      | Solution type                                     |
      | Logging Service                                   |
      | Languages                                         |
      | English                                           |
      | Status                                            |
      | Completed                                         |
      | Geographical coverage                             |
      | Belgium                                           |
      | Release version                                   |
      | v1.1                                              |
      | Release date                                      |
      | 21/12/2022                                        |
      | Only facilitators and authors can create content. |
      | Non moderated                                     |

  # This is a regression test for the entities that include a hashmark on their Uri.
  # @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-3225
  Scenario: Regression test for Uris that include a '#'.
    Given the following solution:
      | uri         | http://solution/example1/test#        |
      | title       | Information sharing protocols         |
      | description | Handling information sharing securely |
      | logo        | logo.png                              |
      | state       | published                             |
    When I go to the homepage of the "Information sharing protocols" solution
    Then I should see the heading "Information sharing protocols"
    And I should not see the text "Page not found"

  Scenario: Custom pages should not be visible on the solution homepage
    Given the following solution:
      | title            | Jira restarters                      |
      | description      | Rebooting solves all issues          |
      | documentation    | text.pdf                             |
      | content creation | registered users                     |
      | landing page     | http://foo-example.com/landing       |
      | webdav creation  | no                                   |
      | webdav url       | http://joinup.eu/solution/foo/webdav |
      | wiki             | http://example.wiki/foobar/wiki      |
      | state            | published                            |
    And news content:
      | title                             | body                             | solution        | topic                   | spatial coverage | state     |
      | Jira will be down for maintenance | As always, during business hours | Jira restarters | Statistics and Analysis | Luxembourg       | published |
    And custom_page content:
      | title            | body                                       | solution        | state     |
      | Maintenance page | Jira is re-indexing. Go and drink a coffee | Jira restarters | published |
    When I go to the homepage of the "Jira restarters" solution
    Then a tour should be available
    And I should see the "Jira will be down for maintenance" tile
    And I should not see the "Maintenance page" tile

  Scenario: A link to the first collection a solution is affiliated to should be shown in the solution header.
    Given collections:
      | title              | state     |
      | Disappointed Steel | published |
      | Random Arm         | published |
    And the following solutions:
      | title       | state     | collections                   |
      | Robotic arm | published | Disappointed Steel            |
      | ARM9        | published | Disappointed Steel,Random Arm |

    When I go to the homepage of the "Robotic arm" solution
    Then I should see the link "Disappointed Steel"

    When I go to the homepage of the "ARM9" solution
    Then I should see the link "Disappointed Steel"
    But I should not see the link "Random Arm"

  Scenario: Test that a pager is shown on the solution page when needed.
    Given owner content:
      | name                  | owner type |
      | Chiricahua Foundation | Company    |
    And contact_information content:
      | name     | email                |
      | Geronimo | geronimo@example.com |
    And the following solutions:
      | title             | description     | logo     | state     | owner                 | contact information | solution type | topic       |
      | Chiricahua Server | Serving the web | logo.png | published | Chiricahua Foundation | Geronimo            | Business      | E-inclusion |
    # There should not be a pager when the solution is empty.
    When I go to the homepage of the "Chiricahua Server" solution
    Then I should not see the "Pager" region

    # The pager should only appear when there are more than 12 items.
    Given distribution content:
      | title           | description    | parent            | state     |
      | Distribution 1  | Description 1  | Chiricahua Server | published |
      | Distribution 2  | Description 2  | Chiricahua Server | published |
      | Distribution 3  | Description 3  | Chiricahua Server | published |
      | Distribution 4  | Description 4  | Chiricahua Server | published |
      | Distribution 5  | Description 5  | Chiricahua Server | published |
      | Distribution 6  | Description 6  | Chiricahua Server | published |
      | Distribution 7  | Description 7  | Chiricahua Server | published |
      | Distribution 8  | Description 8  | Chiricahua Server | published |
      | Distribution 9  | Description 9  | Chiricahua Server | published |
      | Distribution 10 | Description 10 | Chiricahua Server | published |
      | Distribution 11 | Description 11 | Chiricahua Server | published |
      | Distribution 12 | Description 12 | Chiricahua Server | published |
    When I go to the homepage of the "Chiricahua Server" solution
    Then I should not see the "Pager" region
    And I should not see the "Distribution 13" tile

    Given distribution content:
      | title           | description    | parent            | state     |
      | Distribution 13 | Description 13 | Chiricahua Server | published |
    When I go to the homepage of the "Chiricahua Server" solution
    Then I should see the following links:
      | Current page    |
      | Go to page 2    |
      | Go to next page |
      | Go to last page |
    But I should not see the following links:
      | Go to first page    |
      | Go to previous page |
      | Go to page 3        |

    When I click "Go to page 2"
    Then I should see the following links:
      | Go to first page    |
      | Go to previous page |
      | Go to page 1        |
      | Current page        |
    And I should see the "Distribution 13" tile
    But I should not see the following links:
      | Go to next page |
      | Go to last page |
      | Go to page 3    |
    And I should not see the "Distribution 12" tile

    Given distribution content:
      | title           | description    | parent            | state     |
      | Distribution 14 | Description 14 | Chiricahua Server | published |
      | Distribution 15 | Description 15 | Chiricahua Server | published |
      | Distribution 16 | Description 16 | Chiricahua Server | published |
      | Distribution 17 | Description 17 | Chiricahua Server | published |
      | Distribution 18 | Description 18 | Chiricahua Server | published |
      | Distribution 19 | Description 19 | Chiricahua Server | published |
      | Distribution 20 | Description 20 | Chiricahua Server | published |
      | Distribution 21 | Description 21 | Chiricahua Server | published |
      | Distribution 22 | Description 22 | Chiricahua Server | published |
      | Distribution 23 | Description 23 | Chiricahua Server | published |
      | Distribution 24 | Description 24 | Chiricahua Server | published |
      | Distribution 25 | Description 25 | Chiricahua Server | published |

    When I go to the homepage of the "Chiricahua Server" solution
    Then I should see the following links:
      | Current page    |
      | Go to page 2    |
      | Go to page 3    |
      | Go to next page |
      | Go to last page |
    But I should not see the following links:
      | Go to first page    |
      | Go to previous page |

    When I click "Go to page 2"
    Then I should see the following links:
      | Go to first page    |
      | Go to previous page |
      | Go to page 1        |
      | Current page        |
      | Go to page 3        |
      | Go to next page     |
      | Go to last page     |
    And I should see the "Distribution 24" tile
    And I should not see the "Distribution 25" tile
    When I click "Go to page 3"
    Then I should see the following links:
      | Go to first page    |
      | Go to previous page |
      | Go to page 1        |
      | Go to page 2        |
      | Current page        |
    But I should not see the following links:
      | Go to next page |
      | Go to last page |

    When I delete the "Distribution 13" distribution
    And I delete the "Distribution 14" distribution
    And I delete the "Distribution 15" distribution
    And I delete the "Distribution 16" distribution
    And I delete the "Distribution 17" distribution
    And I delete the "Distribution 18" distribution
    And I delete the "Distribution 19" distribution
    And I delete the "Distribution 20" distribution
    And I delete the "Distribution 21" distribution
    And I delete the "Distribution 22" distribution
    And I delete the "Distribution 23" distribution
    And I delete the "Distribution 24" distribution
    And I delete the "Distribution 25" distribution

    When I go to the homepage of the "Chiricahua Server" solution
    Then I should not see the "Pager" region

    # The pager being visible in one solution should not affect other solutions.
    Given the following solution:
      | title       | Chiricahua Solr       |
      | description | Searching your datas  |
      | state       | published             |
      | owner       | Chiricahua Foundation |
    And distribution content:
      | title               | description   | parent          | state     |
      | Solr distribution 1 | Description 1 | Chiricahua Solr | published |
    When I go to the homepage of the "Chiricahua Solr" solution
    Then I should not see the "Pager" region

  @javascript
  Scenario: Test that up to 7 topic terms are visible in the solution overview header.
    Given the following solutions:
      | title      | description        | logo     | state     | topic                                                                                                                              |
      | All topics | Bring in EVERYONE! | logo.png | published | Finance in EU, Supplier exchange, E-health, HR, Employment and Support Allowance, Statistics and Analysis, E-inclusion, Demography |

    When I go to the "All topics" solution
    Then I should see the text "Topic" in the "Header"
    And I should see the following links:
      | Demography                       |
      | E-health                         |
      | E-inclusion                      |
      | Employment and Support Allowance |
      | Finance in EU                    |
      | HR                               |
      | Statistics and Analysis          |
    And I should not see the link "Supplier exchange"
    When I click "HR"
    Then the url should match "/search"
    Then the "HR" content checkbox item should be selected
    Then the "Solution" content checkbox item should be selected
    Then I should see the following facet summary "HR, Solution"
