@api @terms @group-d
Feature: Global search
  As a user of the site I can find content through the global search.

  @javascript
  Scenario: Anonymous user can find items
    Given the following collection:
      | title            | Molecular cooking collection |
      | logo             | logo.png                     |
      | moderation       | no                           |
      | topic            | Demography                   |
      | spatial coverage | Belgium                      |
      | state            | validated                    |
    And the following solutions:
      | title          | collection                   | description                                                                                                                          | topic      | spatial coverage | state     |
      | Spherification | Molecular cooking collection | Spherification is the culinary process of shaping a liquid into spheres                                                              | Demography | European Union   | validated |
      | Foam           | Molecular cooking collection | "The use of foam in cuisine has been used in many forms in the history of cooking:whipped cream, meringue, and mousse are all foams" |            |                  | validated |
    And news content:
      | title                | headline              | body             | collection                   | topic                   | spatial coverage | state     |
      | El Tuba de Los Lobos | El Celler de Can Roca | The best in town | Molecular cooking collection | Statistics and Analysis | Luxembourg       | validated |
      | Dummy title 1        | Dummy news 1          | Dummy body       | Molecular cooking collection | E-inclusion             | Luxembourg       | validated |
      | Dummy title 2        | Dummy news 2          | Dummy body       | Molecular cooking collection | E-inclusion             | Luxembourg       | validated |
      | Dummy title 3        | Dummy news 3          | Dummy body       | Molecular cooking collection | E-inclusion             | Luxembourg       | validated |
      | Dummy title 4        | Dummy news 4          | Dummy body       | Molecular cooking collection | E-inclusion             | Luxembourg       | validated |

    Given I am logged in as a user with the "authenticated" role
    Then I visit the search page
    # All content is visible.
    Then I should see the "Molecular cooking collection" tile
    And I should see the "El Celler de Can Roca" tile
    And I should see the "Spherification" tile
    And I should see the "Foam" tile
    # Terms are sorted alphabetically
    And the "topic" checkbox facet should allow selecting the following values "Info (1), Statistics and Analysis (1), Social and Political (6), Demography (2), E-inclusion (4)"
    Then I click show more from the "spatial coverage" facet
    And the "spatial coverage" checkbox facet should allow selecting the following values "Belgium (1), European Union (1), Luxembourg (5)"

    # Check that only one search field is available. In an earlier version of
    # Joinup there were two search fields, but this was confusing users.
    And there should be exactly 1 "search field" on the page

    When I check the "Social and Political (6)" checkbox from the "topic" facet
    Then the "Social and Political" content checkbox item should be selected
    And the "topic" checkbox facet should allow selecting the following values "Info (1), Statistics and Analysis (1), Social and Political (6), Demography (2), E-inclusion (4)"
    # The tiles appear because the parent term is selected even though they do not have a direct reference there.
    And I should see the "Dummy news 1" tile
    And I should see the "Dummy news 2" tile
    And I should see the "Dummy news 3" tile
    And I should see the "Dummy news 4" tile

    # Test the topic facet. The space prefixing "Demography" is due to the hierarchy.
    When I check the "Demography (2)" checkbox from the "topic" facet
    Then the "Demography" content checkbox item should be selected
    # The selected option moves to the last position by default.
    And the "topic" checkbox facet should allow selecting the following values "Info (1), Statistics and Analysis (1), Social and Political (6), Demography (2), E-inclusion (4)"
    Then I click show more from the "spatial coverage" facet
    And the "spatial coverage" checkbox facet should allow selecting the following values "Belgium (1), European Union (1)"

    And I should see the "Molecular cooking collection" tile
    And I should see the "Spherification" tile
    But I should not see the "El Celler de Can Roca" tile
    And I should not see the "Foam" tile

    # Test the spatial coverage facet.
    When I check the "Belgium (1)" checkbox from the "spatial coverage" facet
    And the "Belgium" content checkbox item should be selected
    Then I click show more from the "spatial coverage" facet
    And the "spatial coverage" checkbox facet should allow selecting the following values "Belgium (1), European Union (1)"
    And the "topic" checkbox facet should allow selecting the following values "Social and Political (1), Demography (1)"
    And I should see the "Molecular cooking collection" tile
    But I should not see the "El Celler de Can Roca" tile
    And I should not see the "Spherification" tile
    And I should not see the "Foam" tile

    Given I am on the search page
    When I check the "News (5)" checkbox from the "Content types" facet
    Then the "News" content checkbox item should be selected
    And the "Content types" checkbox facet should allow selecting the following values "Collection (1), Solutions (2), News (5)"

    When I check the "Solutions (2)" checkbox from the "Content types" facet
    Then the "Solutions" content checkbox item should be selected
    And the "News" content checkbox item should be selected
    And I should see the following facet summary "Solutions, News"
    Then the "Content types" checkbox facet should allow selecting the following values "Collection (1), Solutions (2), News (5)"
    And the "topic" checkbox facet should allow selecting the following values "Info (1), Statistics and Analysis (1), Social and Political (5), Demography (1), E-inclusion (4)"
    Then I click show more from the "spatial coverage" facet
    And the "spatial coverage" checkbox facet should allow selecting the following values "European Union (1), Luxembourg (5)"
    And I should not see the "Molecular cooking collection" tile
    And I should see the "El Celler de Can Roca" tile
    But I should see the "Spherification" tile
    And I should see the "Foam" tile

    # Launch a text search.
    When I click "Clear filters"
    Then I open the search bar by clicking on the search icon
    And I enter "Cooking" in the search bar and press enter
    Then I should see the "Molecular cooking collection" tile
    And I should see the "Foam" tile
    But I should not see the "Spherification" tile
    And I should not see the "El Celler de Can Roca" tile

  @javascript
  Scenario: Alphabetical order for the spatial coverage in the search page.
    Given the following owner:
      | name              | type    |
      | Responsible owner | Company |
    And the following contact:
      | name  | Go-to contact     |
      | email | go-to@example.com |
    And the following collections:
      | title            | description                                          | abstract                       | state     |
      | Collection alpha | <p>collection <strong>beta</strong> description.</p> | The collection gamma abstract. | validated |
      | Col for Sol      | <p>collection for the solution.</p>                  | The col for sol abstract.      | validated |
    And event content:
      | title             | short title       | body                                | spatial coverage | agenda         | location       | organisation        | scope         | keywords | collection       | state     |
      | Event Omega       | Event short delta | The epsilon event content.          | Greece           | Event agenda.  | Some place     | European Commission | International | Alphabet | Collection alpha | validated |
      | Alternative event | Alt event         | This event stays in the background. | Luxembourg       | To be planned. | Event location | Event organisation  |               |          | Collection alpha | validated |
    And document content:
      | title          | document type | short title          | body                                    | spatial coverage | keywords | collection       | state     |
      | Document omega | Document      | Document short delta | A document consists of epsilon strings. | Luxembourg       | Alphabet | Collection alpha | validated |

    When I visit the search page

    Then I click show more from the "spatial coverage" facet
    And the "spatial coverage" checkbox facet should allow selecting the following values "Greece (1), Luxembourg (2)"
    When I check the "Luxembourg (2)" checkbox from the "spatial coverage" facet
    Then the "Luxembourg" content checkbox item should be selected
    And I should see the following search header in the correct order:
      | SEARCH RESULTS (2) |
      | Sort by:           |
    And I should see the text "SEARCH RESULTS (2)"
    # The countries are still sorted alphabetically even though the Luxembourg value is selected and has more results.
    Then I click show more from the "spatial coverage" facet
    And the "spatial coverage" checkbox facet should allow selecting the following values "Greece (1), Luxembourg (2)"

  Scenario: Content can be found with a full-text search.
    Given the following owner:
      | name              | type    |
      | Responsible owner | Company |
    And the following contact:
      | name  | Go-to contact     |
      | email | go-to@example.com |
    And the following collections:
      | title            | description                                          | abstract                       | state     |
      | Collection alpha | <p>collection <strong>beta</strong> description.</p> | The collection gamma abstract. | validated |
      | Col for Sol      | <p>collection for the solution.</p>                  | The col for sol abstract.      | validated |
    And the following solutions:
      | title          | description                                                | keywords | owner             | contact information | collection  | state     |
      | Solution alpha | <p>This is the solution <strong>beta</strong> description. | Alphabet | Responsible owner | Go-to contact       | Col for Sol | validated |
    And the following releases:
      | title         | release number | release notes                               | keywords | is version of  | owner             | contact information | state     |
      | Release Alpha | 1              | <p>Release notes for <em>beta</em> changes. | Alphabet | Solution alpha | Responsible owner | Go-to contact       | validated |
    And the following distributions:
      | title              | description                                    | parent        | access url |
      | Distribution alpha | <p>A simple beta distribution description.</p> | Release Alpha | test.zip   |
    And the following licences:
      | title         | description                         |
      | Licence Alpha | A beta description for the licence. |
    And news content:
      | title      | headline            | body                      | keywords | collection       | state     |
      | News omega | News headline delta | The epsilon news content. | Alphabet | Collection alpha | validated |
    And event content:
      | title             | short title       | body                                | agenda         | location       | organisation        | scope         | keywords | collection       | solution       | state     |
      | Event Omega       | Event short delta | The epsilon event content.          | Event agenda.  | Some place     | European Commission | International | Alphabet |                  | Solution alpha | validated |
      | Alternative event | Alt event         | This event stays in the background. | To be planned. | Event location | Event organisation  |               |          | Collection alpha |                | validated |
    And document content:
      | title          | document type | short title          | body                                    | keywords | collection       | state     |
      | Document omega | Document      | Document short delta | A document consists of epsilon strings. | Alphabet | Collection alpha | validated |
    And discussion content:
      | title            | body                                                              | solution       | state     |
      | Discussion omega | <p>Does anybody has idea why this <em>epsilon</em> is everywhere? | Solution alpha | validated |
    And custom_page content:
      | title      | body                                     | collection       |
      | Page omega | This is just an epsilon but should work. | Collection alpha |
    And video content:
      | title       | body          | field_video                                 | collection       |
      | Video alpha | Slap like now | https://www.youtube.com/watch?v=JhGf8ZY0tN8 | Collection alpha |
    And users:
      | Username     | E-mail                      | First name | Family name | Organisation |
      | jenlyle      | jenessa.carlyle@example.com | Jenessa    | Carlyle     | Clyffco      |
      | ulyssesfrees | ulysses.freeman@example.com | Ulysses    | Freeman     | Omero snc    |

    When I visit the search page
    Then the "Content types" checkbox facet should allow selecting the following values "Collections (2), Solution (1), News (1), Events (2), Document (1), Discussion (1), Release (1), Custom page (1), Licence (1), Video (1)"

    # "Alpha" is used in all the rdf entities titles.
    When I enter "Alpha" in the search bar and press enter
    And I should see the following search header in the correct order:
      | SEARCH RESULTS (5) |
      | Sort by:           |
    Then I should see the text "SEARCH RESULTS (5)"
    And the page should show the tiles "Collection alpha, Solution alpha, Release Alpha, Licence Alpha, Video alpha"

    # "Omega" is used in all the node entities titles. Since the content of
    # custom pages is added to their collection, we also match the collection.
    When I enter "omega" in the search bar and press enter
    Then I should see the text "SEARCH RESULTS (6)"
    And the page should show the tiles "Collection alpha, News headline delta, Event Omega, Document omega, Discussion omega, Page omega"

    # "Beta" is used in all the rdf entities body fields.
    When I enter "beta" in the search bar and press enter
    Then I should see the text "SEARCH RESULTS (4)"
    And the page should show the tiles "Collection alpha, Solution alpha, Release Alpha, Licence Alpha"

    # "Epsilon" is used in all the node entities body fields.
    When I enter "epsilon" in the search bar and press enter
    Then the page should show the tiles "Collection alpha, News headline delta, Event Omega, Document omega, Discussion omega, Page omega"

    # "Alphabet" is used in all the keywords fields.
    When I enter "Alphabet" in the search bar and press enter
    Then the page should show the tiles "Solution alpha, Release Alpha, News headline delta, Event Omega, Document omega"

    # "Gamma" is used in the collection abstract.
    When I enter "gamma" in the search bar and press enter
    Then the page should show the tiles "Collection alpha"

    # "Delta" is used in headline and short titles.
    When I enter "delta" in the search bar and press enter
    Then the page should show the tiles "News headline delta, Event Omega, Document omega"

    # Search for the event fields: agenda, location, address, organisation, scope.
    When I enter "agenda" in the search bar and press enter
    Then the page should show the tiles "Event Omega"
    When I enter "location" in the search bar and press enter
    Then the page should show the tiles "Alternative event"
    When I enter "place" in the search bar and press enter
    Then the page should show the tiles "Event Omega"
    When I enter "organisation" in the search bar and press enter
    Then the page should show the tiles "Alternative event"
    When I enter "international" in the search bar and press enter
    Then the page should show the tiles "Event Omega"

    # The owner and contact information names should be indexed inside the solutions/releases they are linked to.
    When I enter "responsible" in the search bar and press enter
    Then the page should show the tiles "Solution alpha, Release Alpha"
    # Visit the homepage to be sure that the test fetches the correct updated page.
    When I go to the homepage
    And I enter "contact" in the search bar and press enter
    Then the page should show the tiles "Solution alpha, Release Alpha"

    # Users should be found by first name, family name and organisation.
    When I enter "Jenessa" in the search bar and press enter
    Then the page should show the tiles "Jenessa Carlyle"
    When I enter "freeman" in the search bar and press enter
    Then the page should show the tiles "Ulysses Freeman"
    When I enter "clyffco" in the search bar and press enter
    Then the page should show the tiles "Jenessa Carlyle"
    When I enter "Omero snc" in the search bar and press enter
    Then the page should show the tiles "Ulysses Freeman"

  Scenario: Advanced search
    # A search icon is shown in the navbar, except on the search page.
    Given I am on the homepage
    Then I should see the link "advanced search"
    When I click "advanced search"
    Then I should be on the advanced search page
    But I should not see the link "advanced search"
    And I should not see search bar icon in navbar
    Given I visit the collection overview
    Then I should not see the link "advanced search"
    And I should see search bar icon in navbar
    Then I should toggle search bar in navbar
    And I should see search bar in navbar

  @wip
  # This tests shows a regression to be fixed in ISAICP-6917.
  # See https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-6917
  Scenario: Collections and solutions are shown first in search results with the same relevance.
    Given collections:
      | title                           | description                         | state     |
      | Ornithology: the study of birds | Ornithology is a branch of zoology. | validated |
      | Husky Flying Xylophone          | A strange instrument.               | validated |
    And the following solution:
      | title       | Bird outposts in the wild            |
      | collection  | Ornithology: the study of birds      |
      | description | Exotic wings and where to find them. |
      | state       | validated                            |
    And custom_page content:
      | title           | body                                  | collection                      |
      | Disturbed birds | Flocks of trained pigeons flying off. | Ornithology: the study of birds |
    And news content:
      | title                               | headline                            | body                            | collection                      | state     |
      | Chickens are small birds            | Chickens are small birds            | Birds domesticated in India.    | Ornithology: the study of birds | validated |
      | Found a xylophone from 1600 in Asia | Found a xylophone from 1600 in Asia | Oldest instrument of this type. | Husky Flying Xylophone          | validated |
    And event content:
      | title         | body                   | collection                      | state     |
      | Bird spotting | Roosters crow at dawn. | Ornithology: the study of birds | validated |
    And discussion content:
      | title                             | body                    | collection                      | state     |
      | Best place to find an exotic bird | Somewhere exotic maybe? | Ornithology: the study of birds | validated |
    And user:
      | Username    | Bird watcher |
      | First name  | Bird         |
      | Family name | Birdman      |

    # The bird is the word... to search.
    When I enter "Bird" in the search bar and press enter
    Then I should see the following tiles in the correct order:
      | Chickens are small birds          |
      | Ornithology: the study of birds   |
      | Bird outposts in the wild         |
      | Disturbed birds                   |
      | Bird spotting                     |
      | Best place to find an exotic bird |
      | Bird Birdman                      |

  @clearStaticCache
  Scenario: Solutions and/or releases are found by their distribution keyword.
    Given the following licences:
      | title      |
      | Apache-2.0 |
      | LGPL       |
    And the following solution:
      | title | Zzolution |
      | state | validated |

    When I enter "ZzoluDistro" in the search bar and press enter
    Then I should see "No content found for your search."

    # Add distribution, child of solution.
    Given the following distribution:
      | title                    | ZzoluDistro                     |
      | parent                   | Zzolution                       |
      | description              | Übermensch foot size            |
      | access url               | http://example.com/zzolu-distro |
      | licence                  | Apache-2.0                      |
      | format                   | HTML                            |
      | representation technique | Datalog                         |

    When I enter "zzoludistro" in the search bar and press enter
    Then the page should show only the tiles "Zzolution"
    When I enter "ubermensch" in the search bar and press enter
    Then the page should show only the tiles "Zzolution"
    When I enter "zzolu-distro" in the search bar and press enter
    Then the page should show only the tiles "Zzolution"
    When I enter "apache" in the search bar and press enter
    # Also the licence itself is retrieved.
    Then the page should show only the tiles "Apache-2.0,Zzolution"
    When I enter "HTML" in the search bar and press enter
    Then the page should show only the tiles "Zzolution"
    When I enter "Datalog" in the search bar and press enter
    Then the page should show only the tiles "Zzolution"

    Given I am logged in as a moderator
    When I go to the edit form of the "ZzoluDistro" distribution
    And I fill in "Title" with "DistroZzolu"
    And I enter "Nietzsche's" in the "Description" wysiwyg editor
    And I press "Remove"
    And I set a remote URL "http://example.com/guzzle" to "Access URL"
    And I select "LGPL" from "Licence"
    And I select "CSV" from "Format"
    And I select "Human Language" from "Representation technique"
    And I press "Save"
    Then I should see the heading "DistroZzolu"

    # Repeat the previous searches to prove that the initial keywords were
    # removed from the Search API index.
    Given I am an anonymous user
    When I enter "zzoludistro" in the search bar and press enter
    Then I should see "No content found for your search."
    When I enter "ubermensch" in the search bar and press enter
    Then I should see "No content found for your search."
    When I enter "zzolu-distro" in the search bar and press enter
    Then I should not see the text "Search Results"
    And I should see "No content found for your search."
    When I enter "apache" in the search bar and press enter
    Then the page should show only the tiles "Apache-2.0"
    When I enter "HTML" in the search bar and press enter
    Then I should see "No content found for your search."
    When I enter "Datalog" in the search bar and press enter
    Then I should see "No content found for your search."

    # Search now with the new keywords.
    When I enter "distrozzolu" in the search bar and press enter
    Then the page should show only the tiles "Zzolution"
    When I enter "nietzsche" in the search bar and press enter
    Then the page should show only the tiles "Zzolution"
    When I enter "guzzle" in the search bar and press enter
    Then the page should show only the tiles "Zzolution"
    When I enter "lGPL" in the search bar and press enter
    # Also the licence itself is retrieved.
    Then the page should show only the tiles "LGPL,Zzolution"
    When I enter "CSV" in the search bar and press enter
    Then the page should show only the tiles "Zzolution"
    When I enter "Human Language" in the search bar and press enter
    Then the page should show only the tiles "Zzolution"

    Given I delete the "DistroZzolu" asset distribution

    # The parent solution has been re-indexed without distribution data.
    When I enter "distrozzolu" in the search bar and press enter
    Then I should see "No content found for your search."
    When I enter "nietzsche" in the search bar and press enter
    Then I should see "No content found for your search."
    When I enter "guzzle" in the search bar and press enter
    Then I should see "No content found for your search."
    When I enter "lGPL" in the search bar and press enter
    Then the page should show only the tiles "LGPL"
    When I enter "CSV" in the search bar and press enter
    Then I should see "No content found for your search."
    When I enter "Human Language" in the search bar and press enter
    Then I should see "No content found for your search."

    # Add a new distribution, child of a release.
    Given the following release:
      | title         | Releazz   |
      | state         | validated |
      | is version of | Zzolution |

    When I enter "ReleazzDistro" in the search bar and press enter
    Then I should see "No content found for your search."

    Given the following distribution:
      | title                    | ReleazzDistro                     |
      | parent                   | Releazz                           |
      | description              | Dracula                           |
      | access url               | http://example.com/releazz-distro |
      | licence                  | Apache-2.0                        |
      | format                   | HTML                              |
      | representation technique | Datalog                           |

    When I enter "releazzDistro" in the search bar and press enter
    Then the page should show only the tiles "Releazz"
    When I enter "dracula" in the search bar and press enter
    Then the page should show only the tiles "Releazz"
    When I enter "releazz-distro" in the search bar and press enter
    Then the page should show only the tiles "Releazz"
    When I enter "apache" in the search bar and press enter
    # Also the licence itself is retrieved.
    Then the page should show only the tiles "Apache-2.0,Releazz"
    When I enter "HTML" in the search bar and press enter
    Then the page should show only the tiles "Releazz"
    When I enter "Datalog" in the search bar and press enter
    Then the page should show only the tiles "Releazz"

    Given I am logged in as a moderator
    When I go to the edit form of the "ReleazzDistro" distribution
    And I fill in "Title" with "DistroReleazz"
    And I fill in "Description" with "Zorro"
    And I press "Remove"
    And I set a remote URL "http://example.com/mishmash" to "Access URL"
    And I select "LGPL" from "Licence"
    And I select "CSV" from "Format"
    And I select "Human Language" from "Representation technique"
    And I press "Save"

    # Repeat the previous searches to prove that the initial keywords were
    # removed from the Search API index.
    Given I am an anonymous user
    When I enter "releazzDistro" in the search bar and press enter
    Then I should see "No content found for your search."
    When I enter "dracula" in the search bar and press enter
    Then I should see "No content found for your search."
    When I enter "releazz-distro" in the search bar and press enter
    Then I should see "No content found for your search."
    When I enter "apache" in the search bar and press enter
    Then the page should show only the tiles "Apache-2.0"
    When I enter "HTML" in the search bar and press enter
    Then I should see "No content found for your search."
    When I enter "Datalog" in the search bar and press enter
    Then I should see "No content found for your search."

    # Search now with the new keywords.
    When I enter "dIstrOreleazz" in the search bar and press enter
    Then the page should show only the tiles "Releazz"
    When I enter "zoRRo" in the search bar and press enter
    Then the page should show only the tiles "Releazz"
    When I enter "mishMash" in the search bar and press enter
    Then the page should show only the tiles "Releazz"
    When I enter "LGpl" in the search bar and press enter
    # Also the licence itself is retrieved.
    Then the page should show only the tiles "LGPL,Releazz"
    When I enter "CSV" in the search bar and press enter
    Then the page should show only the tiles "Releazz"
    When I enter "Human Language" in the search bar and press enter
    Then the page should show only the tiles "Releazz"

    Given I delete the "DistroReleazz" asset distribution

    # The parent release has been re-indexed without distribution data.
    When I enter "dIstrOreleazz" in the search bar and press enter
    Then I should see "No content found for your search."
    When I enter "zoRRo" in the search bar and press enter
    Then I should see "No content found for your search."
    When I enter "mishMash" in the search bar and press enter
    Then I should see "No content found for your search."
    When I enter "lGPL" in the search bar and press enter
    Then the page should show only the tiles "LGPL"
    When I enter "CSV" in the search bar and press enter
    Then I should see "No content found for your search."
    When I enter "Human Language" in the search bar and press enter
    Then I should see "No content found for your search."

  Scenario: Collections are found by their keywords.
    Given the following collection:
      | title    | Collection sample       |
      | keywords | unique, key-definitions |
      | state    | validated               |

    When I enter "key-definitions" in the search bar and press enter
    Then the page should show only the tiles "Collection sample"

    When I enter "unique" in the search bar and press enter
    Then the page should show only the tiles "Collection sample"

  @javascript
  Scenario: Users are able to select the sort order.
    Given collections:
      | title             | description       | state     |
      | Custom collection | Some custom data. | validated |
    And news content:
      | title               | headline                           | body                                                              | collection        | state     | created    | changed    |
      | Quantum information | Relativity is the word             | No one cares about the body.                                      | Custom collection | validated | 01/01/2019 | 03/08/2019 |
      | The uneven universe | Relativity news: Relativity theory | I do care about the relativity keyword in the body.               | Custom collection | validated | 02/01/2019 | 02/08/2019 |
      | The earth is square | Absolutely nonsense                | Some news are not worth it but I will add relativity here anyway. | Custom collection | validated | 03/01/2019 | 01/08/2019 |

    When I am on the homepage
    And I enter "Relativity" in the search bar and press enter
    Then the option "Relevance" should be selected
    And I should see the text "SEARCH RESULTS (3)"
    And I should see the following tiles in the correct order:
      | Relativity news: Relativity theory |
      | Relativity is the word             |
      | Absolutely nonsense                |
    And I should be on "/search?keys=Relativity&sort_by=relevance"

    Given I select "Creation Date" from "Sort by"
    Then I should see the following tiles in the correct order:
      | Absolutely nonsense                |
      | Relativity news: Relativity theory |
      | Relativity is the word             |
    And I should be on "/search?keys=Relativity&sort_by=creation-date"

    Given I select "Last Updated Date" from "Sort by"
    Then I should see the following tiles in the correct order:
      | Relativity is the word             |
      | Relativity news: Relativity theory |
      | Absolutely nonsense                |
    And I should be on "/search?keys=Relativity&sort_by=last-updated-date"

  @javascript
  Scenario: Anonymous user can find facets summary
    Given the following collection:
      | title            | Radio cooking collection |
      | logo             | logo.png                 |
      | moderation       | no                       |
      | topic            | Demography               |
      | spatial coverage | Belgium                  |
      | state            | validated                |
    And the following solutions:
      | title    | collection               | description                                                                                                                          | topic      | spatial coverage | state     |
      | Spheres  | Radio cooking collection | Spherification is the culinary process of shaping a liquid into spheres                                                              | Demography | European Union   | validated |
      | Movistar | Radio cooking collection | "The use of foam in cuisine has been used in many forms in the history of cooking:whipped cream, meringue, and mousse are all foams" |            |                  | validated |
    And news content:
      | title           | body             | collection               | topic                   | spatial coverage | state     |
      | El Cabo da Roca | The best in town | Radio cooking collection | Statistics and Analysis | Luxembourg       | validated |
      | Funny news 1    | Dummy body       | Radio cooking collection | E-inclusion             | Luxembourg       | validated |
      | Funny news 2    | Dummy body       | Radio cooking collection | E-inclusion             | Luxembourg       | validated |
      | Funny news 3    | Dummy body       | Radio cooking collection | E-inclusion             | Luxembourg       | validated |
      | Funny news 4    | Dummy body       | Radio cooking collection | E-inclusion             | Luxembourg       | validated |

    Given I am logged in as a user with the "authenticated" role
    When I visit the search page
    And I check the "Solutions (2)" checkbox from the "Content types" facet
    And I check the "News (5)" checkbox from the "Content types" facet
    And I should see the following facet summary "Solutions, News"

    When I click "Clear filters"
    And I check the "News (5)" checkbox from the "Content types" facet
    And I should see the following facet summary "News"

    # Check if facet summary was remove correctly.
    When I click "Clear filters"
    And I check the "News (5)" checkbox from the "Content types" facet
    And I check the "Collection (1)" checkbox from the "Content types" facet
    Then I should see the following facet summary "Collection, News"
    When I remove the following facet summary "News"
    Then the page should show only the tiles "Radio cooking collection"

  @javascript
  Scenario: Anonymous user can find facets summary
    Given the following collection:
      | title            | Radio cooking collection |
      | logo             | logo.png                 |
      | moderation       | no                       |
      | topic            | Demography               |
      | spatial coverage | Belgium                  |
      | state            | validated                |
    And the following solutions:
      | title    | collection               | description                                                                                                                          | topic      | spatial coverage | state     |
      | Spheres  | Radio cooking collection | Spherification is the culinary process of shaping a liquid into spheres                                                              | Demography | European Union   | validated |
      | Movistar | Radio cooking collection | "The use of foam in cuisine has been used in many forms in the history of cooking:whipped cream, meringue, and mousse are all foams" |            |                  | validated |
    And news content:
      | title           | body             | collection               | topic                   | spatial coverage | state     |
      | El Cabo da Roca | The best in town | Radio cooking collection | Statistics and Analysis | Luxembourg       | validated |
      | Funny news 1    | Dummy body       | Radio cooking collection | E-inclusion             | Luxembourg       | validated |
      | Funny news 2    | Dummy body       | Radio cooking collection | E-inclusion             | Luxembourg       | validated |
      | Funny news 3    | Dummy body       | Radio cooking collection | E-inclusion             | Luxembourg       | validated |
      | Funny news 4    | Dummy body       | Radio cooking collection | E-inclusion             | Luxembourg       | validated |

    Given I am logged in as a user with the "authenticated" role
    When I visit the search page
    And I should not see search bar icon in navbar
    And I enter "funn" in the search bar
    And I wait for AJAX to finish
    Then I should see the following search suggestions:
      | funni |
      | funny |

    When I enter "bott" in the search bar
    And I wait for AJAX to finish
    Then I should see the following search suggestions:
      | body |

    When I check the "Solutions (2)" checkbox from the "Content types" facet
    And I check the "News (5)" checkbox from the "Content types" facet
    Then I should see the following facet summary "Solutions, News"

    When I click "Clear filters"
    And I check the "News (5)" checkbox from the "Content types" facet
    Then I should see the following facet summary "News"

    # Check if facet summary was remove correctly.
    When I click "Clear filters"
    And I check the "News (5)" checkbox from the "Content types" facet
    And I check the "Collection (1)" checkbox from the "Content types" facet
    Then I should see the following facet summary "Collection, News"
    When I remove the following facet summary "News"
    Then the page should show only the tiles "Radio cooking collection"

    # Check if search autocomplete works in navbar.
    When I visit the collection overview
    And I should see search bar icon in navbar
    Then I should toggle search bar in navbar
    And I enter "funn" in the search bar
    And I wait for AJAX to finish
    Then I should see the following search suggestions:
      | funni |
      | funny |

    # Check if search autocomplete works in homepage's header search bar.
    Given I am on the homepage
    Then I should see the search bar in homepage's header
    And I enter "funn" in the search bar
    And I wait for AJAX to finish
    Then I should see the following search suggestions:
      | funni |
      | funny |

    # Check if search did you mean works.
    Given I visit the search page
    And I enter "histore" in the search bar
    Then I submit the search by pressing enter
    And I should see the text "Did you mean: history ?"
    And I click "history"
    Then I should see the text "SEARCH RESULTS (1)"

    Then I enter "cosine" in the search bar
    And I submit the search by pressing enter
    And I should see the text "Did you mean: cuisine ?"
    And I click "cuisine"
    Then I should see the text "SEARCH RESULTS (1)"
    And I should see the "Movistar" tile

  @terms
  Scenario: Filter on date
    Given collection:
      | title | Mice in space |
      | state | validated     |
    And document content:
      | title        | collection    | topic                                  | state     | logo     | body                                                                                                                                                                                            |
      | Microgravity | Mice in space | HR, Statistics and Analysis, E-justice | validated | alan.jpg | Conventional studies investigating the effects of reduced gravity on muscle mass and function have used a ground control group that is not directly comparable to the space experimental group. |
    And event content:
      | title           | collection    | topic                    | state     | logo        | body                                                                                                                                                                                  |
      | Stay at the ISS | Mice in space | EU and European Policies | validated | charles.jpg | Two groups of mice (six per group) were housed aboard the International Space Station for 35 days. One group was subjected to artificial gravity (1 g) and the other to microgravity. |
    And news content:
      | title           | body                                       | collection    | topic                   | spatial coverage | state     | logo        | created    | changed    |
      | Muscle atrophy  | Researchers from the University of Tsukuba | Mice in space | Finance in EU           | Luxembourg       | validated | blaise.jpg  | 01/01/2019 | 01/01/2019 |
      | El Cabo da Roca | The best in town                           | Mice in space | Statistics and Analysis | Luxembourg       | validated | charles.jpg | 01/12/2020 | 01/12/2020 |
      | Funny news 1    | Dummy body                                 | Mice in space | HR                      | Luxembourg       | validated | ada.png     | 05/01/2019 | 05/01/2019 |
      | Funny news 2    | Dummy body                                 | Mice in space | E-inclusion             | Luxembourg       | validated | alan.jpg    | 06/21/2019 | 06/21/2019 |
      | Funny news 3    | Dummy body                                 | Mice in space | E-inclusion             | Luxembourg       | validated | charles.jpg | 12/31/2021 | 12/31/2021 |
      | Funny news 4    | Dummy body                                 | Mice in space | HR                      | Luxembourg       | validated | ada.png     | 06/25/2020 | 06/25/2020 |
      | Funny news 5    | Dummy body                                 | Mice in space | HR                      | Luxembourg       | validated | ada.png     | 01/01/2021 | 01/01/2021 |
      | Dummy news 1    | Dummy body                                 | Mice in space | Statistics and Analysis | Luxembourg       | validated | charles.jpg | 09/24/2019 | 09/24/2019 |
      | Dummy news 2    | Dummy body                                 | Mice in space | E-inclusion             | Luxembourg       | validated | alan.jpg    | 01/18/2021 | 01/18/2021 |
      | Dummy news 3    | Dummy body                                 | Mice in space | Statistics and Analysis | Luxembourg       | validated | ada.png     | 08/01/2019 | 08/01/2019 |
      | Dummy news 4    | Dummy body                                 | Mice in space | E-inclusion             | Luxembourg       | validated | charles.jpg | 09/18/2019 | 09/18/2019 |

    When I visit the search page
    Then I should see the 3 tiles with image

    Then I go to "/search?sort_by=relevance&page=1"
    And I should see the 0 tiles with image

    # Filter content from 01-01-2020 to 30-06-2020.
    # Only "El Cabo da Roca" and "Funny news 4" should appear.
    Then I go to "/search?f[0]=creation_date:(min:1577880000,max:1593518400)"
    And I should see the text "SEARCH RESULTS (2)"

    Then I go to "/search?f[0]=creation_date:(min:1577836800,max:)"
    And I should see the text "SEARCH RESULTS (8)"

    Then I go to "/search?f[0]=creation_date:(min:,max:1609113600)"
    And I should see the text "SEARCH RESULTS (8)"

    Then I go to "/search?f[0]=creation_date:(min:,max:)"
    And I should see the text "SEARCH RESULTS (14)"

  Scenario: Max 3 topic terms appear in each search result with a suffix for the rest.
    Given the following collection:
      | title      | Max 3 topic collection                                                             |
      | moderation | no                                                                                 |
      | topic      | Finance in EU, Supplier exchange, Demography, E-inclusion, Statistics and Analysis |
      | state      | validated                                                                          |
    When I visit the search page
    Then there should be 3 topics in the "Max 3 topic collection" card
    And I should see the text "+2 topics" in the "Max 3 topic collection" card

  Scenario: In search result I can see updated date label.
    Given collections:
      | title             | description       | state     |
      | Custom collection | Some custom data. | validated |
    And news content:
      | title               | headline                           | body                                                              | collection        | state     | created    | changed    |
      | Quantum information | Relativity is the word             | No one cares about the body.                                      | Custom collection | validated | 01/01/2019 | 03/08/2019 |
      | The uneven universe | Relativity news: Relativity theory | I do care about the relativity keyword in the body.               | Custom collection | validated | 02/01/2019 | 02/08/2019 |
      | The earth is square | Absolutely nonsense                | Some news are not worth it but I will add relativity here anyway. | Custom collection | validated | 03/01/2019 | 01/08/2019 |
    When I visit the search page
    Then I should see the text "Updated Date: 08/03/2019" in the "Relativity is the word" card
    And I should see the text "Updated Date: 08/02/2019" in the "Relativity news: Relativity theory" card
    And I should see the text "Updated Date: 08/01/2019" in the "Absolutely nonsense" card

  Scenario: Regression test for ISAICP-7078
  See https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-7078

    Given collections:
      | title            | description       | state     |
      | Asset collection | Some custom data. | validated |
    And the following solution:
      | title            | My asset solution                    |
      | collection       | Asset collection                     |
      | description      | A asset sample solution              |
      | documentation    | text.pdf                             |
      | content creation | registered users                     |
      | landing page     | http://foo-example.com/landing       |
      | webdav creation  | no                                   |
      | webdav url       | http://joinup.eu/solution/foo/webdav |
      | wiki             | http://example.wiki/foobar/wiki      |
      | state            | validated                            |
    And the following releases:
      | title  | documentation | release number | release notes | creation date    | is version of     | state     | status    |
      | Delta1 | text.pdf      | 1.0.0          | New 1.0       | 2017-11-11T11:11 | My asset solution | validated | Completed |
      | Delta2 | text.pdf      | 2.0.0          | New 2.0       | 2017-12-11T11:11 | My asset solution | validated | Completed |
      | Delta3 | text.pdf      | 3.0.0          | New 3.0       | 2018-11-11T11:11 | My asset solution | validated | Completed |
      | Delta4 | text.pdf      | 4.0.0          | New 4.0       | 2019-11-11T11:11 | My asset solution | validated | Completed |
      | Delta5 | text.pdf      | 5.0.0          | New 5.0       | 2020-11-11T11:11 | My asset solution | validated | Completed |
      | Delta6 | text.pdf      | 6.0.0          | New 6.0       | 2021-11-11T11:11 | My asset solution | validated | Completed |
      | Delta7 | text.pdf      | 7.0.0          | New 7.0       | 2021-12-11T11:11 | My asset solution | validated | Completed |
      | Delta8 | text.pdf      | 8.0.0          | New 8.0       | 2018-10-11T11:11 | My asset solution | validated | Completed |

    When I go to "/search?f[0]=type%3Aasset_release"
    And I should see the text "SEARCH RESULTS (8)"
    Then I should see the text "New 1.0" in the "Delta1" card
    And I should see the text "New 2.0" in the "Delta2" card
    And I should see the text "New 3.0" in the "Delta3" card
    And I should see the text "New 4.0" in the "Delta4" card
    And I should see the text "New 5.0" in the "Delta5" card
    And I should see the text "New 6.0" in the "Delta6" card
    And I should see the text "New 7.0" in the "Delta7" card
    And I should see the text "New 8.0" in the "Delta8" card
