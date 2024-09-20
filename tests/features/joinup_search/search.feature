@api @group-g
Feature: Global search
  As a user of the site I can find content through the global search.

  @javascript
  Scenario: Anonymous user can find items
    Given collection content:
      | title                        | logo     | moderation | topic      | spatial coverage | state     |
      | Molecular cooking collection | logo.png | no         | Demography | Belgium          | published |
    Given solution content:
      | title          | collection                   | description                                                                                                                          | topic      | spatial coverage | state     |
      | Spherification | Molecular cooking collection | Spherification is the culinary process of shaping a liquid into spheres                                                              | Demography | European Union   | published |
      | Foam           | Molecular cooking collection | "The use of foam in cuisine has been used in many forms in the history of cooking:whipped cream, meringue, and mousse are all foams" | eGov       |                  | published |
    And news content:
      | title                | headline              | field_paragraphs_body | collection                   | topic                   | spatial coverage | state     |
      | El Tuba de Los Lobos | El Celler de Can Roca | The best in town      | Molecular cooking collection | Statistics and Analysis | Luxembourg       | published |
      | Dummy news 1         | Dummy title 1         | Dummy body            | Molecular cooking collection | E-inclusion             | Luxembourg       | published |
      | Dummy news 2         | Dummy title 2         | Dummy body            | Molecular cooking collection | E-inclusion             | Luxembourg       | published |
      | Dummy news 3         | Dummy title 3         | Dummy body            | Molecular cooking collection | E-inclusion             | Luxembourg       | published |
      | Dummy news 4         | Dummy title 4         | Dummy body            | Molecular cooking collection | E-inclusion             | Luxembourg       | published |

    When I visit the search page
    Then the response should not contain "data-drupal-facet-id=\"status\""

    # Create some archived content too.
    Given news content:
      | title      | headline    | field_paragraphs_body | collection                   | topic       | spatial coverage | state    |
      | Rummy news | Rummy title | Dummy body            | Molecular cooking collection | E-inclusion | Luxembourg       | archived |
    And event content:
      | title       | field_paragraphs_body | collection                   | topic       | spatial coverage | state    |
      | Rummy event | Dummy body            | Molecular cooking collection | E-inclusion | Luxembourg       | archived |
    And document content:
      | title          | document type | short title          | field_paragraphs_body | collection                   | topic       | spatial coverage | state    |
      | Rummy document | Document      | Rummy document short | Dummy body            | Molecular cooking collection | E-inclusion | Luxembourg       | archived |
    And discussion content:
      | title            | body       | collection                   | topic       | spatial coverage | state    |
      | Rummy discussion | Dummy body | Molecular cooking collection | E-inclusion | Luxembourg       | archived |
    Given solution content:
      | title         | collection                   | description                            | state    | topic             |
      | Spring device | Molecular cooking collection | A mechanical device that stores energy | archived | Supplier exchange |

    # Test that archived items are not available in the autocomplete.
    When I visit the search page
    And I focus on the search input
    And I enter "Rummy" in the search bar
    And I wait for AJAX to finish
    Then I should not see the text "Rummy news"
    And I should not see the text "Rummy event"
    And I should not see the text "Rummy document"
    And I should not see the text "Rummy discussion"

    # Test that archived items are not available in the search results.
    When I enter "Rummy" in the search bar and press enter
    Then I should not see the text "Rummy news"
    And I should not see the text "Rummy event"
    And I should not see the text "Rummy document"
    And I should not see the text "Rummy discussion"

    # But clicking the "Show only Archived items" checkbox should show them.
    When I check the "Show only Archived items" checkbox from the "Status" facet
    Then I should see the "Rummy news" tile
    And I should see the "Rummy event" tile
    And I should see the "Rummy document" tile
    And I should see the "Rummy discussion" tile

    Given I am logged in as a user with the "authenticated" role
    Then I visit the search page
    # All content is visible.
    Then I should see the "Molecular cooking collection" tile
    And I should see the "El Tuba de Los Lobos" tile
    And I should see the "Spherification" tile
    And I should see the "Foam" tile
    # Terms are sorted alphabetically
    And the "topic" checkbox facet should allow selecting the following values "eGov (1), Info (1), Statistics and Analysis (1), Social and Political (6), Demography (2), E-inclusion (4)"
    Then I click show more from the "spatial coverage" facet
    And the "spatial coverage" checkbox facet should allow selecting the following values "Belgium (1), European Union (1), Luxembourg (5)"

    # Check that only one search field is available. In an earlier version of
    # Joinup there were two search fields, but this was confusing users.
    And there should be exactly 1 "search field" on the page

    When I check the "Social and Political (6)" checkbox from the "topic" facet
    Then the "Social and Political" content checkbox item should be selected
    And the "topic" checkbox facet should allow selecting the following values "eGov (1), Info (1), Statistics and Analysis (1), Social and Political (6), Demography (2), E-inclusion (4)"
    # The tiles appear because the parent term is selected even though they do not have a direct reference there.
    And I should see the "Dummy news 1" tile
    And I should see the "Dummy news 2" tile
    And I should see the "Dummy news 3" tile
    And I should see the "Dummy news 4" tile
    And I should not see the "Rummy news 5" tile
    And I should not see the "Rummy news 6" tile

    # Test ability to tick many topics.
    When I check the "Demography (2)" checkbox from the "topic" facet
    Then I should see the text "SEARCH RESULTS (2)"
    When I check the "E-inclusion (4)" checkbox from the "topic" facet
    Then I should see the text "SEARCH RESULTS (6)"
    And the "Demography" content checkbox item should be selected
    And the "E-inclusion" content checkbox item should be selected

    # The selected option moves to the last position by default.
    When I click "E-inclusion"
    Then I should see the text "SEARCH RESULTS (2)"
    And the "topic" checkbox facet should allow selecting the following values "eGov (1), Info (1), Statistics and Analysis (1), Social and Political (6), Demography (2), E-inclusion (4)"
    When I click show more from the "spatial coverage" facet
    Then the "spatial coverage" checkbox facet should allow selecting the following values "Belgium (1), European Union (1)"
    And I should see the "Molecular cooking collection" tile
    And I should see the "Spherification" tile
    But I should not see the "El Tuba de Los Lobos" tile
    And I should not see the "Foam" tile

    # Test the spatial coverage facet.
    When I check the "Belgium (1)" checkbox from the "spatial coverage" facet
    And the "Belgium" content checkbox item should be selected
    Then I click show more from the "spatial coverage" facet
    And the "spatial coverage" checkbox facet should allow selecting the following values "Belgium (1), European Union (1)"
    And the "topic" checkbox facet should allow selecting the following values "Social and Political (1), Demography (1)"
    And I should see the "Molecular cooking collection" tile
    But I should not see the "El Tuba de Los Lobos" tile
    And I should not see the "Spherification" tile
    And I should not see the "Foam" tile

    # Test ability to tick many geographical areas.
    Given I am on the search page
    And I click show more from the "spatial coverage" facet
    And I check the "Belgium (1)" checkbox from the "spatial coverage" facet
    Then I should see the text "SEARCH RESULTS (1)"
    When I click show more from the "spatial coverage" facet
    And I check the "European Union (1)" checkbox from the "spatial coverage" facet
    Then I should see the text "SEARCH RESULTS (2)"

    Given I am on the search page
    When I check the "User profiles (1)" checkbox from the "Content types" facet
    Then the "User profiles" content checkbox item should be selected
    And I should see 1 tile

    Given I click "Clear filters"
    When I check the "News (5)" checkbox from the "Content types" facet
    Then the "News" content checkbox item should be selected
    And the "Content types" checkbox facet should allow selecting the following values "Collection (1), Solutions (2), News (5), User profiles (1)"

    When I check the "Solutions (2)" checkbox from the "Content types" facet
    Then the "Solutions" content checkbox item should be selected
    And the "News" content checkbox item should be selected
    And I should see the following facet summary "Solutions, News"
    Then the "Content types" checkbox facet should allow selecting the following values "Collection (1), Solutions (2), News (5), User profiles (1)"
    And the "topic" checkbox facet should allow selecting the following values "eGov (1), Info (1), Statistics and Analysis (1), Social and Political (5), Demography (1), E-inclusion (4)"
    Then I click show more from the "spatial coverage" facet
    And the "spatial coverage" checkbox facet should allow selecting the following values "European Union (1), Luxembourg (5)"
    And I should not see the "Molecular cooking collection" tile
    And I should see the "El Tuba de Los Lobos" tile
    But I should see the "Spherification" tile
    And I should see the "Foam" tile

    # Launch a text search.
    When I click "Clear filters"
    Then I focus on the search input
    And I enter "Cooking" in the search bar and press enter
    Then I should see the "Molecular cooking collection" tile
    And I should see the "Foam" tile
    But I should not see the "Spherification" tile
    And I should not see the "El Tuba de Los Lobos" tile

    Given I am on the search page
    When I check the "Show only Archived items" checkbox from the "Status" facet
    And I should not see the "Molecular cooking collection" tile
    And I should not see the "Spherification" tile
    And I should not see the "Foam" tile
    And I should not see the "El Tuba de Los Lobos" tile
    And I should not see the "Dummy news 1" tile
    And I should not see the "Dummy news 2" tile
    And I should not see the "Dummy news 3" tile
    And I should not see the "Dummy news 4" tile
    And I should see the "Spring device" tile
    And I should see the text "Archived" in the "Spring device" tile

    Then I check the "Show only Archived items" checkbox from the "Status" facet
    And I should see the "Molecular cooking collection" tile
    And I should see the "Spherification" tile
    And I should see the "Foam" tile
    And I should see the "El Tuba de Los Lobos" tile
    And I should see the "Dummy news 1" tile
    And I should see the "Dummy news 2" tile
    And I should see the "Dummy news 3" tile
    And I should see the "Dummy news 4" tile
    And I should not see the "Spring device" tile

  @javascript
  Scenario: Alphabetical order for the spatial coverage in the search page.
    Given owner content:
      | name              | owner type |
      | Responsible owner | Company    |
    And contact_information content:
      | name          | email             |
      | Go-to contact | go-to@example.com |
    And collection content:
      | title            | description                                          | abstract                       | state     |
      | Collection alpha | <p>collection <strong>beta</strong> description.</p> | The collection gamma abstract. | published |
      | Col for Sol      | <p>collection for the solution.</p>                  | The col for sol abstract.      | published |
    And event content:
      | title             | short title       | field_paragraphs_body               | spatial coverage | agenda         | location       | organisation        | scope         | keywords | collection       | state     |
      | Event Omega       | Event short delta | The epsilon event content.          | Greece           | Event agenda.  | Some place     | European Commission | International | Alphabet | Collection alpha | published |
      | Alternative event | Alt event         | This event stays in the background. | Luxembourg       | To be planned. | Event location | Event organisation  |               |          | Collection alpha | published |
    And document content:
      | title          | document type | short title          | field_paragraphs_body                   | spatial coverage | keywords | collection       | state     |
      | Document omega | Document      | Document short delta | A document consists of epsilon strings. | Luxembourg       | Alphabet | Collection alpha | published |

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
    Given owner content:
      | name              | owner type |
      | Responsible owner | Company    |
    And contact_information content:
      | name          | email             | state     |
      | Go-to contact | go-to@example.com | validated |
    And collection content:
      | title            | description                                          | abstract                       | state     |
      | Collection alpha | <p>collection <strong>beta</strong> description.</p> | The collection gamma abstract. | published |
      | Col for Sol      | <p>collection for the solution.</p>                  | The col for sol abstract.      | published |
    Given solution content:
      | title          | description                                                | keywords | owner             | contact information | collection  | state     |
      | Solution alpha | <p>This is the solution <strong>beta</strong> description. | Alphabet | Responsible owner | Go-to contact       | Col for Sol | published |
    And release content:
      | title         | release number | release notes                               | keywords | is version of  | owner             | contact information | state     |
      | Release Alpha | 1              | <p>Release notes for <em>beta</em> changes. | Alphabet | Solution alpha | Responsible owner | Go-to contact       | published |
    And distribution content:
      | title              | description                                    | release       | access url | state     |
      | Distribution alpha | <p>A simple beta distribution description.</p> | Release Alpha | test.zip   | published |
    And licence content:
      | title         | description                         |
      | Licence Alpha | A beta description for the licence. |
    And news content:
      | title      | headline            | field_paragraphs_body     | keywords | collection       | state     |
      | News omega | News headline delta | The epsilon news content. | Alphabet | Collection alpha | published |
    And event content:
      | title             | short title       | abstract                                         | field_paragraphs_body               | agenda         | location       | organisation        | scope         | keywords | collection       | solution       | state     |
      | Event Omega       | Event short delta | The epsilon event content but abstract.          | The epsilon event content.          | Event agenda.  | Some place     | European Commission | International | Alphabet |                  | Solution alpha | published |
      | Alternative event | Alt event         | This event stays in the background but abstract. | This event stays in the background. | To be planned. | Event location | Event organisation  |               |          | Collection alpha |                | published |
    And document content:
      | title          | document type | short title          | field_paragraphs_body                   | keywords | collection       | state     |
      | Document omega | Document      | Document short delta | A document consists of epsilon strings. | Alphabet | Collection alpha | published |
    And discussion content:
      | title            | body                                                              | solution       | state     |
      | Discussion omega | <p>Does anybody has idea why this <em>epsilon</em> is everywhere? | Solution alpha | published |
    And custom_page content:
      | title      | body                                     | collection       | state     | keywords |
      | Page omega | This is just an epsilon but should work. | Collection alpha | published | Alphabet |
    And users:
      | Username     | E-mail                      | First name | Family name | Organisation |
      | jenlyle      | jenessa.carlyle@example.com | Jenessa    | Carlyle     | Clyffco      |
      | ulyssesfrees | ulysses.freeman@example.com | Ulysses    | Freeman     | Omero snc    |

    When I visit the search page
    Then the "Content types" checkbox facet should allow selecting the following values "Collections (2), Solution (1), News (1), Events (2), Document (1), Discussion (1), Release (1), Custom page (1), Licence (1), User profiles (2)"

    # "A beta" word does not show "You must include at least one positive keyword with 3 characters or more." error message.
    When I enter "A beta" in the search bar and press enter
    Then I should not see the error message "You must include at least one positive keyword with 3 characters or more."

    # "Alpha" is used in all the rdf entities titles.
    When I enter "Alpha" in the search bar and press enter
    And I should see the following search header in the correct order:
      | SEARCH RESULTS (4) |
      | Sort by:           |
    Then I should see the text "SEARCH RESULTS (4)"
    And the page should show the tiles "Collection alpha, Solution alpha, Release Alpha, Licence Alpha"

    # "Omega" is used in all the node entities titles. Since the content of
    # custom pages is added to their collection, we also match the collection.
    When I enter "omega" in the search bar and press enter
    Then I should see the text "SEARCH RESULTS (6)"
    And the page should show the tiles "Collection alpha, News omega, Event Omega, Document omega, Discussion omega, Page omega"
    And I should see the text "The epsilon event content but abstract."

    # "Beta" is used in all the rdf entities body fields.
    When I enter "beta" in the search bar and press enter
    Then I should see the text "SEARCH RESULTS (4)"
    And the page should show the tiles "Collection alpha, Solution alpha, Release Alpha, Licence Alpha"

    # "Epsilon" is used in all the node entities body fields.
    When I enter "epsilon" in the search bar and press enter
    Then the page should show the tiles "Collection alpha, News omega, Event Omega, Document omega, Discussion omega, Page omega"

    # "Alphabet" is used in all the keywords fields.
    When I enter "Alphabet" in the search bar and press enter
    Then the page should show the tiles "Solution alpha, Release Alpha, News omega, Event Omega, Document omega, Page omega"

    # "Gamma" is used in the collection abstract.
    When I enter "gamma" in the search bar and press enter
    Then the page should show the tiles "Collection alpha"

    # "Delta" is used in headline and short titles.
    When I enter "delta" in the search bar and press enter
    Then the page should show the tiles "News omega, Event Omega, Document omega"

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
    Given collection content:
      | title                           | description                         | state     |
      | Ornithology: the study of birds | Ornithology is a branch of zoology. | published |
      | Husky Flying Xylophone          | A strange instrument.               | published |
    And solution content:
      | title                     | collection                      | description                          | state     |
      | Bird outposts in the wild | Ornithology: the study of birds | Exotic wings and where to find them. | published |
    And custom_page content:
      | title           | body                                  | collection                      | state     |
      | Disturbed birds | Flocks of trained pigeons flying off. | Ornithology: the study of birds | published |
    And news content:
      | title                               | headline                            | field_paragraphs_body           | collection                      | state     |
      | Chickens are small birds            | Chickens are small birds            | Birds domesticated in India.    | Ornithology: the study of birds | published |
      | Found a xylophone from 1600 in Asia | Found a xylophone from 1600 in Asia | Oldest instrument of this type. | Husky Flying Xylophone          | published |
    And event content:
      | title         | field_paragraphs_body  | collection                      | state     |
      | Bird spotting | Roosters crow at dawn. | Ornithology: the study of birds | published |
    And discussion content:
      | title                             | body                    | collection                      | state     |
      | Best place to find an exotic bird | Somewhere exotic maybe? | Ornithology: the study of birds | published |
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

  Scenario: Solutions and/or releases are found by their distribution keyword.
    Given licence content:
      | title      |
      | Apache-2.0 |
      | LGPL       |
    And solution content:
      | title     | state     |
      | Zzolution | published |

    When I enter "ZzoluDistro" in the search bar and press enter
    Then I should see "No content found for your search."

    # Add distribution, child of solution.
    Given distribution content:
      | title       | solution  | description          | access url                      | licence    | format | representation technique | state     |
      | ZzoluDistro | Zzolution | Übermensch foot size | http://example.com/zzolu-distro | Apache-2.0 | ZIP    | Datalog                  | published |

    When I enter "zzoludistro" in the search bar and press enter
    Then the page should show only the tiles "Zzolution"
    When I enter "ubermensch" in the search bar and press enter
    Then the page should show only the tiles "Zzolution"
    When I enter "zzolu-distro" in the search bar and press enter
    Then the page should show only the tiles "Zzolution"
    When I enter "apache" in the search bar and press enter
    # Also the licence itself is retrieved.
    Then the page should show only the tiles "Apache-2.0,Zzolution"
    When I enter "ZIP" in the search bar and press enter
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
    And I press "Update"
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

    Given I delete the "DistroZzolu" distribution

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
    Given release content:
      | title   | release number | state     | is version of |
      | Releazz | 1              | published | Zzolution     |

    When I enter "ReleazzDistro" in the search bar and press enter
    Then I should see "No content found for your search."

    Given distribution content:
      | title         | release | description | access url                        | licence    | format | representation technique | state     |
      | ReleazzDistro | Releazz | Dracula     | http://example.com/releazz-distro | Apache-2.0 | ZIP    | Datalog                  | published |

    When I enter "releazzDistro" in the search bar and press enter
    Then the page should show only the tiles "Releazz"
    When I enter "dracula" in the search bar and press enter
    Then the page should show only the tiles "Releazz"
    When I enter "releazz-distro" in the search bar and press enter
    Then the page should show only the tiles "Releazz"
    When I enter "apache" in the search bar and press enter
    # Also the licence itself is retrieved.
    Then the page should show only the tiles "Apache-2.0,Releazz"
    When I enter "ZIP" in the search bar and press enter
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
    And I press "Update"

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

    Given I delete the "DistroReleazz" distribution

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
    Given collection content:
      | title             | keywords                | state     |
      | Collection sample | unique, key-definitions | published |

    When I enter "key-definitions" in the search bar and press enter
    Then the page should show only the tiles "Collection sample"

    When I enter "unique" in the search bar and press enter
    Then the page should show only the tiles "Collection sample"

  @javascript
  Scenario: Users are able to select the sort order.
    Given collection content:
      | title             | description       | state     |
      | Custom collection | Some custom data. | published |
    And news content:
      | title               | headline                           | field_paragraphs_body                                             | collection        | state     | created    | changed    |
      | Quantum information | Relativity is the word             | No one cares about the body.                                      | Custom collection | published | 01/01/2019 | 03/08/2019 |
      | The uneven universe | Relativity news: Relativity theory | I do care about the relativity keyword in the body.               | Custom collection | published | 02/01/2019 | 02/08/2019 |
      | The earth is square | Absolutely nonsense                | Some news are not worth it but I will add relativity here anyway. | Custom collection | published | 03/01/2019 | 01/08/2019 |
    And event content:
      | title           | collection        | state     | start date   | end date            | state     | created      |
      | Blue revolution | Custom collection | published | now +1 years | now +1 years +1 day | published | now -2 years |
      | Red revolution  | Custom collection | published | now -1 years | now -1 years +1 day | published | now -1 years |

    When I am on the homepage
    And I enter "Relativity" in the search bar and press enter
    Then the option "Relevance" should be selected
    And I should see the text "SEARCH RESULTS (3)"
    And I should see the following tiles in the correct order:
      | The uneven universe |
      | Quantum information |
      | The earth is square |
    And I should be on "/search?keys=Relativity&sort_by=relevance"

    Given I select "Date" from "Sort by"
    Then I should see the following tiles in the correct order:
      | The earth is square |
      | The uneven universe |
      | Quantum information |
    And I should be on "/search?keys=Relativity&sort_by=date"

    Given I select "Last update" from "Sort by"
    Then I should see the following tiles in the correct order:
      | Quantum information |
      | The uneven universe |
      | The earth is square |
    And I should be on "/search?keys=Relativity&sort_by=last-updated-date"

    Then I enter "revolution" in the search bar and press enter
      | Red revolution  |
      | Blue revolution |
    And I should be on "/search?keys=revolution&sort_by=last-updated-date"

    Given I select "Date" from "Sort by"
    Then I should see the following tiles in the correct order:
      | Blue revolution |
      | Red revolution  |
    And I should be on "/search?keys=revolution&sort_by=date"

    When I go to "/search?sort_by=date"
    Then I should see the following tiles in the correct order:
      | Blue revolution     |
      | Custom collection   |
      | The earth is square |
      | The uneven universe |
      | Quantum information |
      | Red revolution      |

  @javascript
  Scenario: Anonymous user can find facets summary
    Given collection content:
      | title                    | logo     | moderation | topic      | spatial coverage | state     |
      | Radio cooking collection | logo.png | no         | Demography | Belgium          | published |
    Given solution content:
      | title    | collection               | description                                                                                                                                                   | topic      | spatial coverage | state     |
      | Spheres  | Radio cooking collection | Spherification is the culinary process of shaping a liquid into spheres. Drupal is the best digital experience platform(DXP) on the web, proudly open source. | Demography | European Union   | published |
      | Movistar | Radio cooking collection | "The use of foam in cuisine has been used in many forms in the history of cooking:whipped cream, meringue, and mousse are all foams", proudly open source.    |            |                  | published |
    And news content:
      | title           | field_paragraphs_body                                                                | collection               | topic                   | spatial coverage | state     |
      | El Cabo da Roca | The best in town, proudly open source.                                               | Radio cooking collection | Statistics and Analysis | Luxembourg       | published |
      | Funny news 1    | Drupal is the best digital experience platform(DXP) on the web, proudly open source. | Radio cooking collection | E-inclusion             | Luxembourg       | published |
      | Funny news 2    | Drupal is the best digital experience platform(DXP) on the web, proudly open source. | Radio cooking collection | E-inclusion             | Luxembourg       | published |
      | Funny news 3    | Drupal is the best digital experience platform(DXP) on the web, proudly open source. | Radio cooking collection | E-inclusion             | Luxembourg       | published |
      | Funny news 4    | Drupal is the best digital experience platform(DXP) on the web, proudly open source. | Radio cooking collection | E-inclusion             | Luxembourg       | published |

    Given I am logged in as a user with the "authenticated" role
    When I visit the search page
    And I should not see search bar icon in navbar
    And I enter "fu" in the "search bar"
    Then I should see the following search suggestions:
      | Radio cooking collection |
      | Spheres                  |

    When I enter "cook" in the "search bar"
    Then I should see the following search suggestions:
      | Movistar                 |
      | Radio cooking collection |

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
    And I enter "fu" in the "search bar"
    Then I should see the following search suggestions:
      | Radio cooking collection |
      | Spheres                  |

    # Check if search autocomplete works in homepage's header search bar.
    Given I am on the homepage
    Then I should see the search bar in homepage's header
    And I enter "fu" in the "search bar"
    Then I should see the following search suggestions:
      | Radio cooking collection |
      | Spheres                  |

    # Check if search did you mean works.
    Given I visit the search page
    And I enter "histore" in the "search bar"
    Then I submit the search by pressing enter
    And I should see the text "Did you mean: history ?"
    And I click "history"
    Then I should see the text "SEARCH RESULTS (1)"

    Then I enter "cosine" in the "search bar"
    And I submit the search by pressing enter
    And I should see the text "Did you mean: cuisine ?"
    And I click "cuisine"
    Then I should see the text "SEARCH RESULTS (1)"
    And I should see the "Movistar" tile

    Then I enter "spheres" in the "search bar"
    Then I should see the following search suggestions:
      | Spheres |

  Scenario: Filter on date
    Given collection content:
      | title         | state     |
      | Mice in space | published |
    And document content:
      | title        | collection    | topic                                  | state     | logo     | field_paragraphs_body                                                                                                                                                                           |
      | Microgravity | Mice in space | HR, Statistics and Analysis, E-justice | published | alan.jpg | Conventional studies investigating the effects of reduced gravity on muscle mass and function have used a ground control group that is not directly comparable to the space experimental group. |
    And event content:
      | title           | collection    | topic                    | state     | logo        | field_paragraphs_body                                                                                                                                                                 |
      | Stay at the ISS | Mice in space | EU and European Policies | published | charles.jpg | Two groups of mice (six per group) were housed aboard the International Space Station for 35 days. One group was subjected to artificial gravity (1 g) and the other to microgravity. |
    And news content:
      | title           | body                                       | collection    | topic                   | spatial coverage | state     | logo        | created    | changed    | published_at |
      | Muscle atrophy  | Researchers from the University of Tsukuba | Mice in space | Finance in EU           | Luxembourg       | published | blaise.jpg  | 01/01/2019 | 01/01/2019 | 01/01/2019   |
      | El Cabo da Roca | The best in town                           | Mice in space | Statistics and Analysis | Luxembourg       | published | charles.jpg | 01/12/2020 | 01/12/2020 | 01/12/2020   |
      | Funny news 1    | Dummy body                                 | Mice in space | HR                      | Luxembourg       | published | ada.png     | 05/01/2019 | 05/01/2019 | 05/01/2019   |
      | Funny news 2    | Dummy body                                 | Mice in space | E-inclusion             | Luxembourg       | published | alan.jpg    | 06/21/2019 | 06/21/2019 | 06/21/2019   |
      | Funny news 3    | Dummy body                                 | Mice in space | E-inclusion             | Luxembourg       | published | charles.jpg | 12/31/2021 | 12/31/2021 | 12/31/2021   |
      | Funny news 4    | Dummy body                                 | Mice in space | HR                      | Luxembourg       | published | ada.png     | 06/25/2020 | 06/25/2020 | 06/25/2020   |
      | Funny news 5    | Dummy body                                 | Mice in space | HR                      | Luxembourg       | published | ada.png     | 01/01/2021 | 01/01/2021 | 01/01/2021   |
      | Dummy news 1    | Dummy body                                 | Mice in space | Statistics and Analysis | Luxembourg       | published | charles.jpg | 09/24/2019 | 09/24/2019 | 09/24/2019   |
      | Dummy news 2    | Dummy body                                 | Mice in space | E-inclusion             | Luxembourg       | published | alan.jpg    | 01/18/2021 | 01/18/2021 | 01/18/2021   |
      | Dummy news 3    | Dummy body                                 | Mice in space | Statistics and Analysis | Luxembourg       | published | ada.png     | 08/01/2019 | 08/01/2019 | 08/01/2019   |
      | Dummy news 4    | Dummy body                                 | Mice in space | E-inclusion             | Luxembourg       | published | charles.jpg | 09/18/2019 | 09/18/2019 | 09/18/2019   |

    When I visit the search page

    # Filter content from 01-01-2020 to 30-06-2020.
    # Only "El Cabo da Roca" and "Funny news 4" should appear.
    Then I go to "/search?f[0]=date:(min:1577880000,max:1593518400)"
    And I should see the text "SEARCH RESULTS (2)"

    Then I go to "/search?f[0]=date:(min:1577836800,max:)"
    And I should see the text "SEARCH RESULTS (8)"

    Then I go to "/search?f[0]=date:(min:,max:1609113600)"
    And I should see the text "SEARCH RESULTS (8)"

    Then I go to "/search?f[0]=date:(min:,max:)"
    And I should see the text "SEARCH RESULTS (14)"

  Scenario: Max 2 topic terms appear in each search result with a suffix for the rest.
    Given collection content:
      | title                  | moderation | topic                                                                              | state     |
      | Max 2 topic collection | no         | Finance in EU, Supplier exchange, Demography, E-inclusion, Statistics and Analysis | published |
    When I visit the search page
    Then there should be 2 topics in the "Max 2 topic collection" card
    And I should see the text "+3 topics" in the "Max 2 topic collection" card

  Scenario: In search result I can see updated date label.
    Given collection content:
      | title             | description       | state     |
      | Custom collection | Some custom data. | published |
    And news content:
      | title               | headline                           | body                                                              | collection        | state     | created    | changed    |
      | Quantum information | Relativity is the word             | No one cares about the body.                                      | Custom collection | published | 01/01/2019 | 03/08/2019 |
      | The uneven universe | Relativity news: Relativity theory | I do care about the relativity keyword in the body.               | Custom collection | published | 02/01/2019 | 02/08/2019 |
      | The earth is square | Absolutely nonsense                | Some news are not worth it but I will add relativity here anyway. | Custom collection | published | 03/01/2019 | 01/08/2019 |
    When I visit the search page
    Then I should see the text "Last update: 08/03/2019" in the "Quantum information" card
    And I should see the text "Last update: 08/02/2019" in the "The uneven universe" card
    And I should see the text "Last update: 08/01/2019" in the "The earth is square" card

  @javascript @loggedErrors
  Scenario: Regression test for warning generated by search_api_solr.
  See https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-8210

    # Check if suggestion does not generate any warnings.
    Given I visit the search page
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
    Given owner content:
      | name        | owner type |
      | Renew owner | Company    |
    And contact_information content:
      | name           | email              |
      | Gor-to contact | gor-to@example.com |
    And collection content:
      | title            | description                                          | abstract                      | state     |
      | Pull alpha       | <p>collection <strong>pull</strong> description.</p> | The collection pull abstract. | published |
      | Col for Sol javi | <p>collection for the solution javi.</p>             | Random abstract.              | published |
      | Roll alpha       | <p>collection <strong>roll</strong> description.</p> | The collection roll abstract. | archived  |
      | Col for Sol net  | <p>collection for the solution net.</p>              | Loky and Rocky.               | archived  |
    Given solution content:
      | title         | description                                                | abstract                    | keywords | owner       | contact information | collection       | state     |
      | Solution pull | <p>This is the solution <strong>pull</strong> description. | The solution pull abstract. | Alphabet | Renew owner | Gor-to contact      | Pull alpha       | published |
      | Solution javi | <p>This is the solution <strong>javi</strong> description. |                             | Alphabet | Renew owner | Gor-to contact      | Col for Sol javi | published |
      | Solution roll | <p>This is the solution <strong>roll</strong> description. | The solution roll abstract. | Alphabet | Renew owner | Gor-to contact      | Roll alpha       | published |
      | Solution net  | <p>This is the solution <strong>net</strong> description.  |                             | Alphabet | Renew owner | Gor-to contact      | Col for Sol net  | published |

    When I visit the search page
    And I should see the "Pull alpha" tile
    And I should see the "Col for Sol javi" tile
    And I should see the "Solution pull" tile
    And I should see the "Solution javi" tile
    And I should see the "Solution roll" tile
    And I should see the "Solution net" tile
    And I should not see the "Roll alpha" tile
    And I should not see the "Col for Sol net" tile
    And I should see the text "The collection pull abstract." in the "Pull alpha" card
    And I should see the text "Random abstract." in the "Col for Sol javi" card
    And I should see the text "The solution pull abstract." in the "Solution pull" card
    And I should see the text "This is the solution javi description" in the "Solution javi" card
    And I should see the text "The solution roll abstract." in the "Solution roll" card
    And I should see the text "This is the solution net description" in the "Solution net" card

    Then I check the "Show only Archived items" checkbox from the "Status" facet
    And I should not see the "Pull alpha" tile
    And I should not see the "Col for Sol javi" tile
    And I should not see the "Solution pull" tile
    And I should not see the "Solution javi" tile
    And I should not see the "Solution roll" tile
    And I should not see the "Solution net" tile
    And I should see the "Roll alpha" tile
    And I should see the "Col for Sol net" tile
    And I should see the text "The collection roll abstract." in the "Roll alpha" card
    And I should see the text "Loky and Rocky." in the "Col for Sol net" card
