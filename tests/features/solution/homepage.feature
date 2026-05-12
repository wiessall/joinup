@api @group-h
Feature: Solution homepage
  In order get an idea of what a solution is about
  As a user of the website
  I need to be able see an introduction of the solution on its homepage

  Scenario: The solution homepage shows basic information about the solution
    Given the following "owner" content:
      | name          |
      | Awesome owner |
    Given the following "solution" content:
      | title     | description                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      | owner         | topic                   | solution type                           | language | status    | published at    | modification date | abstract                   |
      | Petri net | "<p>A <b>Petri net</b>, also known as a <b>place/transition (PT) net</b>, is one of several <a href=\"#mathematical\">mathematical</a> modeling languages for the description of distributed systems. It is a class of discrete event dynamic system. A Petri net is a directed bipartite graph, in which the nodes represent transitions (i.e. events that may occur, represented by bars) and places (i.e. conditions, represented by circles). The directed arcs describe which places are pre- and/or postconditions for which transitions (signified by arrows). Some sources state that Petri nets were invented in August 1939 by Carl Adam Petri at the age of 13 for the purpose of describing chemical processes.</p>" | Awesome owner | Demography, E-inclusion | Non-binding Instrument, Logging Service | English  | Completed | 2012-12-01T8:43 | 2017-12-01T8:43   | Short abstract description |
    And the following "release" content:
      | title          | release notes    | resources | release number | release notes   | is version of | creation date    | published at     |
      | Petri net v1.1 | A sample release | text.pdf  | 1.1            | Changed release | Petri net     | 2022-12-21T13:00 | 2022-12-21T13:00 |

    When I go to the homepage of the "Petri net" solution
    Then the page should be cacheable
    And I should see the following headings:
      | Quick links          |
      | Detailed information |
      | Moderation           |
    And I should see the following lines of text:
      | Short abstract description                        |
      | Published on                                      |
      | 01/12/2012                                        |
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
      | Release version                                   |
      | v1.1                                              |
      | Release date                                      |
      | 21/12/2022                                        |
      | Only facilitators and authors can create content. |
      | Moderated                                         |

  Scenario: Custom pages should not be visible on the solution homepage
    Given the following "solution" content:
      | title           | content_creation | landing page                   |
      | Jira restarters | registered users | http://foo-example.com/landing |
    And the following "news" content:
      | title                             | solution        |
      | Jira will be down for maintenance | Jira restarters |
    And the following "custom_page" content:
      | title            | body                                       | solution        |
      | Maintenance page | Jira is re-indexing. Go and drink a coffee | Jira restarters |
    When I go to the homepage of the "Jira restarters" solution
    Then a tour should be available
    And I should see the "Jira will be down for maintenance" tile
    And I should not see the "Maintenance page" tile

  Scenario: Test that a pager is shown on the solution page when needed.
    Given the following "solution" content:
      | title             |
      | Chiricahua Server |
    # There should not be a pager when the solution is empty.
    When I go to the homepage of the "Chiricahua Server" solution
    Then I should not see the "Pager" region

    # The pager should only appear when there are more than 12 items.
    Given the following "distribution" content:
      | title           | solution          |
      | Distribution 1  | Chiricahua Server |
      | Distribution 2  | Chiricahua Server |
      | Distribution 3  | Chiricahua Server |
      | Distribution 4  | Chiricahua Server |
      | Distribution 5  | Chiricahua Server |
      | Distribution 6  | Chiricahua Server |
      | Distribution 7  | Chiricahua Server |
      | Distribution 8  | Chiricahua Server |
      | Distribution 9  | Chiricahua Server |
      | Distribution 10 | Chiricahua Server |
      | Distribution 11 | Chiricahua Server |
      | Distribution 12 | Chiricahua Server |
    When I go to the homepage of the "Chiricahua Server" solution
    Then I should not see the "Pager" region
    And I should not see the "Distribution 13" tile

    Given the following "distribution" content:
      | title           | solution          |
      | Distribution 13 | Chiricahua Server |
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

    Given the following "distribution" content:
      | title           | solution          |
      | Distribution 14 | Chiricahua Server |
      | Distribution 15 | Chiricahua Server |
      | Distribution 16 | Chiricahua Server |
      | Distribution 17 | Chiricahua Server |
      | Distribution 18 | Chiricahua Server |
      | Distribution 19 | Chiricahua Server |
      | Distribution 20 | Chiricahua Server |
      | Distribution 21 | Chiricahua Server |
      | Distribution 22 | Chiricahua Server |
      | Distribution 23 | Chiricahua Server |
      | Distribution 24 | Chiricahua Server |
      | Distribution 25 | Chiricahua Server |

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
    Given the following "solution" content:
      | title           |
      | Chiricahua Solr |
    And the following "distribution" content:
      | title               | solution        |
      | Solr distribution 1 | Chiricahua Solr |
    When I go to the homepage of the "Chiricahua Solr" solution
    Then I should not see the "Pager" region

  @javascript
  Scenario: Test that up to 7 topic terms are visible in the solution overview header.
    Given the following "solution" content:
      | title      | topic                                                                                                                              |
      | All topics | Finance in EU, Supplier exchange, E-health, HR, Employment and Support Allowance, Statistics and Analysis, E-inclusion, Demography |

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
