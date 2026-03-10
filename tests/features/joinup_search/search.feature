@api @group-g
Feature: Global search
  As a user of the site I can find content through the global search.

  @javascript
  Scenario: Anonymous user can find items
    Given collection content:
      | title                        | moderation | topic      | spatial coverage | state     |
      | Molecular cooking collection | no         | Demography | Belgium          | published |
    Given solution content:
      | title          | collection                   | description                                                | topic      | domain           | state     |
      | Spherification | Molecular cooking collection | Spherification is the culinary process of shaping a liquid | Demography | Defense          | published |
      | Foam           | Molecular cooking collection | The use of foam in cuisine has been used in many forms     | eGov       | Economic affairs | published |
    And news content:
      | title                | headline              | field_paragraphs_body | collection                   | topic       | spatial coverage | state     |
      | El Tuba de Los Lobos | El Celler de Can Roca | The best in town      | Molecular cooking collection | E-inclusion | Luxembourg       | published |
      | Dummy news 1         | Dummy title 1         | Dummy body            | Molecular cooking collection | E-inclusion | Luxembourg       | published |

    When I visit the advanced search page
    Then the response should not contain "data-drupal-facet-id=\"status\""

    # Create some archived content too.
    Given news content:
      | title      | headline    | field_paragraphs_body | collection                   | topic       | spatial coverage | state    |
      | Rummy news | Rummy title | Dummy body            | Molecular cooking collection | E-inclusion | Luxembourg       | archived |
    And solution content:
      | title         | collection                   | description                            | state    | topic             |
      | Spring device | Molecular cooking collection | A mechanical device that stores energy | archived | Supplier exchange |

    # Test that archived items are not available in the autocomplete.
    When I visit the advanced search page
    And I focus on the search input
    And I enter "Rummy" in the search bar
    And I wait for AJAX to finish
    Then I should not see the text "Rummy news"

    # Test that archived items are not available in the search results.
    When I enter "Rummy" in the search bar and press enter
    Then I should not see the text "Rummy news"

    # But clicking the "Show only Archived items" checkbox should show them.
    When I check the "Show only Archived items" checkbox from the "Status" facet
    Then I should see the "Rummy news" tile

    # Check facets with authenticated user
    Given I am logged in as a user with the "authenticated" role
    Then I visit the advanced search page
    And I should see the "Molecular cooking collection" tile
    And I should see the "El Tuba de Los Lobos" tile
    And I should see the "Spherification" tile
    And I should see the "Foam" tile
    And there should be exactly 1 "search field" on the page

    # Test ability to tick many topics.
    When I check the "Demography (3)" checkbox from the "topic" facet
    Then I should see the text "SEARCH RESULTS (3)"
    When I check the "E-inclusion (2)" checkbox from the "topic" facet
    Then I should see the text "SEARCH RESULTS (5)"

    # Test the content type facet.
    Given I am on the advanced search page
    When I check the "News (2)" checkbox from the "Content types" facet
    Then the "News" content checkbox item should be selected
    When I check the "Solutions (2)" checkbox from the "Content types" facet
    Then I should see the following facet summary "Solutions, News"
    And I should see the "El Tuba de Los Lobos" tile
    And I should see the "Spherification" tile
    And I should see the "Foam" tile

    # Test the "Solution Domain" facet.
    When I click "Clear filters"
    Then I should not see the "Solution Domain" facet
    When I check the "Solutions (2)" checkbox from the "Content types" facet
    And I should see the "Solution Domain" facet
    When I check the "Defense (1)" checkbox from the "Solution Domain" facet
    Then I should see the "Spherification" tile
    And I should not see the "Foam" tile

    # Launch a text search.
    When I click "Clear filters"
    Then I focus on the search input
    And I enter "Cooking" in the search bar and press enter
    Then I should see the "Molecular cooking collection" tile
    But I should not see the "Foam" tile
    And I should not see the "Spherification" tile

    Given I am on the advanced search page
    When I check the "Show only Archived items" checkbox from the "Status" facet
    And I should see the "Spring device" tile
    And I should see the text "Archived" in the "Spring device" tile

    # Test date filtering.
    Given news content:
      | title           | field_paragraphs_body    | collection                   | topic       | spatial coverage | state     | created    | changed    | published_at |
      | Muscle atrophy  | Researchers from Tsukuba | Molecular cooking collection | Demography  | Luxembourg       | published | 01/01/2019 | 01/01/2019 | 01/01/2019   |
      | El Cabo da Roca | The best in town         | Molecular cooking collection | E-inclusion | Luxembourg       | published | 01/12/2020 | 01/12/2020 | 01/12/2020   |
      | Funny news 1    | Dummy body               | Molecular cooking collection | Demography  | Luxembourg       | published | 06/25/2020 | 06/25/2020 | 06/25/2020   |

    # Filter content from 01-01-2020 to 30-06-2020.
    When I go to "/search?f[0]=date:(min:1577880000,max:1593518400)"
    Then I should see the text "SEARCH RESULTS (2)"

    When I go to "/search?f[0]=date:(min:1577836800,max:)"
    Then I should see the text "SEARCH RESULTS (7)"

  @javascript
  Scenario: Alphabetical order for the spatial coverage and organisation origin in the search page.
    Given owner content:
      | name           | country        |
      | Search owner   | Greece         |
      | European owner | European Union |
    And collection content:
      | title            | description                  | abstract                       | state     |
      | Collection alpha | collection beta description. | The collection gamma abstract. | published |
    And solution content:
      | title            | description                  | abstract                       | owner          |
      | Collection alpha | collection beta description. | The collection gamma abstract. | Search owner   |
      | Collection beta  | collection beta description. | The collection gamma abstract. | European owner |
    And event content:
      | title       | field_paragraphs_body | spatial coverage | collection       | state     |
      | Event Omega | The epsilon event.    | Greece           | Collection alpha | published |
      | Alt event   | Background event.     | Luxembourg       | Collection alpha | published |

    When I visit the advanced search page
    And I should not see the "Organisation country" facet
    When I check the "Solutions (2)" checkbox from the "Content types" facet
    And I should see the "Organisation country" facet
    Then I click "Clear filters"
    Then I click show more from the "spatial coverage" facet
    And the "spatial coverage" checkbox facet should allow selecting the following values "Greece (1), Luxembourg (1)"
    When I check the "Luxembourg (1)" checkbox from the "spatial coverage" facet
    Then the "Luxembourg" content checkbox item should be selected

    Given I click "Clear filters"
    When I check the "Solutions (2)" checkbox from the "Content types" facet
    And I should see the "Organisation country" facet
    And the "Organisation country" checkbox facet should allow selecting the following values "European Union (1), Greece (1)"
    And I check the "European Union (1)" checkbox from the "Organisation country" facet
    Then I should see the "Collection beta" tile
    And I should not see the "Collection alpha" tile

  Scenario: Content can be found with a full-text search.
    Given collection content:
      | title            | description                  | abstract                       | keywords | state     |
      | Collection alpha | collection beta description. | The collection gamma abstract. | Alphabet | published |
    Given solution content:
      | title          | description                | keywords | collection       | state     |
      | Solution alpha | solution beta description. | Alphabet | Collection alpha | published |
    And news content:
      | title      | headline            | field_paragraphs_body     | keywords | collection       | state     |
      | News omega | News headline delta | The epsilon news content. | Alphabet | Collection alpha | published |
    And users:
      | Username | E-mail                      | First name | Family name |
      | jenlyle  | jenessa.carlyle@example.com | Jenessa    | Carlyle     |

    When I visit the advanced search page

    # "Alpha" is used in all the rdf entities titles.
    When I enter "Alpha" in the search bar and press enter
    And I should see the following search header in the correct order:
      | SEARCH RESULTS (2) |
      | Sort by:           |
    Then the page should show the tiles "Collection alpha, Solution alpha"

    # "Omega" is used in all the node entities titles.
    When I enter "omega" in the search bar and press enter
    And the page should show the tiles "News omega"

    # "Beta" is used in all the rdf entities body fields.
    When I enter "beta" in the search bar and press enter
    Then the page should show the tiles "Collection alpha, Solution alpha"

    # "Epsilon" is used in news body.
    When I enter "epsilon" in the search bar and press enter
    Then the page should show the tiles "News omega"

    # Test "Alphabet" in keyword fields.
    When I enter "Alphabet" in the search bar and press enter
    Then the page should show the tiles "Collection alpha, Solution alpha, News omega"

    # "Gamma" is used in the collection abstract.
    When I enter "gamma" in the search bar and press enter
    Then the page should show the tiles "Collection alpha"

    # "Delta" is used in headline.
    When I enter "delta" in the search bar and press enter
    Then the page should show the tiles "News omega"

    # Users should be found by first name and family name.
    When I enter "Jenessa" in the search bar and press enter
    Then the page should show the tiles "Jenessa Carlyle"
    When I enter "carlyle" in the search bar and press enter
    Then the page should show the tiles "Jenessa Carlyle"

  Scenario: Advanced search
    # A search icon is shown in the navbar, except on the search page.
    Given I am on the homepage
    Then I should see the link "advanced search"
    When I click "advanced search"
    Then I should be on the advanced search page
    But I should not see the link "advanced search"
    And I should not see search bar icon in navbar
    Given I visit the collection overview page
    Then I should not see the link "advanced search"
    And I should see search bar icon in navbar
    Then I should toggle search bar in navbar
    And I should see search bar in navbar

  Scenario: Solutions are found by their distribution keyword.
    Given licence content:
      | title      |
      | Apache-2.0 |
    And solution content:
      | title     | state     |
      | Zzolution | published |

    When I enter "ZzoluDistro" in the search bar and press enter
    Then I should see "No content found for your search."

    # Add distribution, child of solution.
    Given distribution content:
      | title       | solution  | description          | resources                       | licence    | format | state     |
      | ZzoluDistro | Zzolution | Übermensch foot size | http://example.com/zzolu-distro | Apache-2.0 | ZIP    | published |

    When I enter "zzoludistro" in the search bar and press enter
    Then the page should show only the tiles "Zzolution"
    When I enter "ubermensch" in the search bar and press enter
    Then the page should show only the tiles "Zzolution"
    When I enter "apache" in the search bar and press enter
    Then the page should show only the tiles "Apache-2.0,Zzolution"
    When I enter "ZIP" in the search bar and press enter
    Then the page should show only the tiles "Zzolution"

    Given I am logged in as a moderator
    When I go to the edit form of the "ZzoluDistro" distribution
    And I fill in "Title" with "DistroZzolu"
    And I enter "Nietzsche's" in the "Description" wysiwyg editor
    And I press "Remove"
    And I press "Add remote file URL"
    And I fill in "resources[1][subform][remote_file_url][0][uri]" with "http://example.com/guzzle"
    And I select "CSV" from "Format"
    And I press "Update"
    Then I should see the heading "DistroZzolu"

    # Repeat the previous searches to prove that the initial keywords were removed.
    Given I am an anonymous user
    When I enter "zzoludistro" in the search bar and press enter
    Then I should see "No content found for your search."
    When I enter "ubermensch" in the search bar and press enter
    Then I should see "No content found for your search."
    When I enter "apache" in the search bar and press enter
    Then the page should show only the tiles "Apache-2.0, Zzolution"

    # Search now with the new keywords.
    When I enter "distrozzolu" in the search bar and press enter
    Then the page should show only the tiles "Zzolution"
    When I enter "nietzsche" in the search bar and press enter
    Then the page should show only the tiles "Zzolution"
    When I enter "CSV" in the search bar and press enter
    Then the page should show only the tiles "Zzolution"

  Scenario: Collections are found by their keywords.
    Given collection content:
      | title             | keywords                      | state     |
      | Collection sample | Alphabet, nintendo64 licences | published |

    When I enter "nintendo64 licences" in the search bar and press enter
    Then the page should show only the tiles "Collection sample"

    When I enter "Alphabet" in the search bar and press enter
    Then the page should show only the tiles "Collection sample"

  @javascript
  Scenario: Users are able to select the sort order.
    Given collection content:
      | title             | description       | state     |
      | Custom collection | Some custom data. | published |
    And news content:
      | title               | headline                           | field_paragraphs_body                   | collection        | state     | created    | changed    |
      | Quantum information | Relativity is the word             | No one cares about the body.            | Custom collection | published | 01/01/2019 | 03/08/2019 |
      | The uneven universe | Relativity news: Relativity theory | I do care about the relativity keyword. | Custom collection | published | 02/01/2019 | 02/08/2019 |
    And event content:
      | title           | collection        | state     | start date   | end date            | created      |
      | Blue revolution | Custom collection | published | now +1 years | now +1 years +1 day | now -2 years |

    When I am on the homepage
    And I enter "Relativity" in the search bar and press enter
    Then the option "Relevance" should be selected
    And I should see the text "SEARCH RESULTS (2)"
    And I should be on "/search?keys=Relativity&sort_by=relevance"

    Given I select "Date" from "Sort by"
    And I should be on "/search?keys=Relativity&sort_by=date"

    Given I select "Last update" from "Sort by"
    Then I should see the following tiles in the correct order:
      | Quantum information |
      | The uneven universe |
    And I should be on "/search?keys=Relativity&sort_by=last-updated-date"

  @javascript
  Scenario: Anonymous user can find facets summary
    Given collection content:
      | title                    | moderation | topic      | spatial coverage | state     |
      | Radio cooking collection | no         | Demography | Belgium          | published |
    Given solution content:
      | title   | collection               | description             | topic      | state     |
      | Spheres | Radio cooking collection | Spherification process. | Demography | published |
    And news content:
      | title        | field_paragraphs_body | collection               | topic       | spatial coverage | state     |
      | El Cabo Roca | The best in town.     | Radio cooking collection | E-inclusion | Luxembourg       | published |

    Given I am logged in as a user with the "authenticated" role
    When I visit the advanced search page
    And I should not see search bar icon in navbar
    When I enter "cook" in the "search bar"
    Then I should see the following search suggestions:
      | Radio cooking collection |

    When I check the "Solution (1)" checkbox from the "Content types" facet
    And I check the "News (1)" checkbox from the "Content types" facet
    Then I should see the following facet summary "Solution, News"

    When I click "Clear filters"
    And I check the "News (1)" checkbox from the "Content types" facet
    Then I should see the following facet summary "News"

    # Check if search autocomplete works in homepage's header search bar.
    Given I am on the homepage
    Then I should see the search bar in homepage's header
    And I enter "cabo" in the "search bar"
    Then I should see the following search suggestions:
      | El Cabo Roca |

    # Check if search executes without errors for misspelled words.
    Given I visit the advanced search page
    And I enter "cato" in the "search bar"
    Then I submit the search by pressing enter
    And I should see the text "Did you mean: cabo ?"

    # Filter content from 01-01-2020 to 30-06-2020.
  Scenario: Max 2 topic terms appear in each search result with a suffix for the rest.
    Given collection content:
      | title                  | moderation | topic                                                                              | state     |
      | Max 2 topic collection | no         | Finance in EU, Supplier exchange, Demography, E-inclusion, Statistics and Analysis | published |
    When I visit the advanced search page
    Then there should be 2 topics in the "Max 2 topic collection" card
    And I should see the text "+3 topics" in the "Max 2 topic collection" card

  Scenario: In search result I can see updated date label.
    Given collection content:
      | title             | description       | state     |
      | Custom collection | Some custom data. | published |
    And news content:
      | title               | headline               | body                     | collection        | state     | created    | changed    |
      | Quantum information | Relativity is the word | No one cares about body. | Custom collection | published | 01/01/2019 | 03/08/2019 |
    When I visit the advanced search page
    Then I should see the text "Last update: 08/03/2019" in the "Quantum information" card

  @javascript @loggedErrors
  Scenario: Regression test for warning generated by search_api_solr.
  See https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-8210

    # Check if suggestion does not generate any warnings.
    Given I visit the advanced search page
    And there are no preexisting log records
    When I enter "-histore" in the search bar
    And I submit the search by pressing enter
    Then the site should not have log records with level lower than "notice"

  Scenario: Regression test for type error.
  See https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-8472

    When I visit "/search?keys=1%00%C0%A7%C0%A2%252527%252522&page=18&sort_by=relevance"
    Then I should get a 200 HTTP response

  @javascript
  Scenario: Search page - Show the abstract or description in the search results.
    Given collection content:
      | title      | description                  | abstract                      | state     |
      | Pull alpha | collection pull description. | The collection pull abstract. | published |
      | Roll alpha | collection roll description. | The collection roll abstract. | archived  |
    Given solution content:
      | title         | description                | abstract                    | collection | state     |
      | Solution pull | solution pull description. | The solution pull abstract. | Pull alpha | published |
      | Solution roll | solution roll description. | The solution roll abstract. | Roll alpha | published |

    When I visit the advanced search page
    And I should see the "Pull alpha" tile
    And I should see the "Solution pull" tile
    And I should see the "Solution roll" tile
    And I should not see the "Roll alpha" tile
    And I should see the text "The collection pull abstract." in the "Pull alpha" card
    And I should see the text "The solution pull abstract." in the "Solution pull" card
    And I should see the text "The solution roll abstract." in the "Solution roll" card

    Then I check the "Show only Archived items" checkbox from the "Status" facet
    And I should not see the "Pull alpha" tile
    And I should not see the "Solution pull" tile
    And I should not see the "Solution roll" tile
    And I should see the "Roll alpha" tile
    And I should see the text "The collection roll abstract." in the "Roll alpha" card

  @javascript
  Scenario: Check topics filter for users.
    Given users:
      | Username | E-mail             | First name | Family name | Professional domain |
      | lyleu    | lyle.u@example.com | Lyle       | Uname       | Demography          |
      | kyleu    | kyle.u@example.com | Kyle       | Freeman     | E-inclusion         |

    When I visit the advanced search page
    Then I should see the text "SEARCH RESULTS (6)"
    When I check the "User profiles (3)" checkbox from the "Content types" facet
    And I should see the "Lyle Uname" tile
    And I should see the "Kyle Freeman" tile
    When I check the "Demography (1)" checkbox from the "topic" facet
    And I should see the "Lyle Uname" tile
    And I should not see the "Kyle Freeman" tile
