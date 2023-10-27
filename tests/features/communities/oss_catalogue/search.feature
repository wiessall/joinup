@api @group_e
Feature: OSS catalogue search
  As a user of the site I can find solutions through the OSS catalogue search.

  Scenario: User can view the OSS catalogue search page.
    Then I visit the OSS search page
    When I should see the heading "Search in EU Open Source Solutions Catalogue"
    And the following fields should be present "Sort by:,Start typing to search…"
    And I should see the text "No content found for your search."
    And I should see the button "Clear filters"

  Scenario: User is able to search.
    # Mimic the "EU OSSC" collection.
    Given collection:
      | uri   | http://data.europa.eu/w21/6d621a47-71ce-4f1a-90e2-8859403d3ecd |
      | title | EU Open Source Solutions Catalogue                             |
      | state | published                                                      |

    When I visit the OSS search page
    Then I see the text "EU Open Source Solutions Catalogue" in the "Header" region
    And I see the text "No Open Source Software Solutions were federated yet" in the "Header" region
    And I see the text "Open source software of interest for Public Administrations" in the "Header" region

    Given oss_solution content:
      | title                           | oss_development_status | oss_source        | oss_categories                                                  | oss_short_description                                                                                                                         | oss_git_description                                                               | status    | oss_release_date |
      | Django-MDQ                      | stable                 | developers_italia | content-management                                              | A lightweight SAML2 MDQ server that implements draft-young-md-query implementation.                                                           |                                                                                   | published | 2022-04-13T13:00 |
      | INGV - QuakeML to NonLinLoc PHS | stable                 | developers_italia | data-analytics,data-collection                                  | Docker image to convert QuakeML format to NonLinLoc PHS format.                                                                               |                                                                                   | published | 2000-07-13T13:00 |
      | NDC Semantic frontend           | beta                   | developers_italia | digital-asset-management                                        |                                                                                                                                               | This is the frontend for the National Data Catalog for Semantic Interoperability. | published | 2023-08-13T13:00 |
      | Software Registry               | development            | developers_italia | application-development,it-development,digital-asset-management | Software Registry makes software development phase simplier by showing extended information about projects and allowing source code searches. |                                                                                   | published | 2000-04-13T13:00 |
      | Immuni                          | obsolete               | developers_italia | data-collection                                                 | Immuni is a mobile app that helps us to fight the COVID-19 pandemic by notifying users at risk of carrying the virus as early as possible     |                                                                                   | published | 2020-09-13T13:00 |

    Then I visit the OSS search page

    # Check the group collection header
    And I see the text "EU Open Source Solutions Catalogue" in the "Header" region
    And I see the text "5 Open Source Software Solutions" in the "Header" region
    And I see the text "Open source software of interest for Public Administrations" in the "Header" region

    # Check main area
    And I should see the text "SEARCH RESULTS (5)"

    # Check tiles.
    And I should see the "Django-MDQ" tile
    And I should see the "INGV - QuakeML to NonLinLoc PHS" tile
    And I should see the text "Docker image to convert QuakeML format to NonLinLoc PHS format." in the "INGV - QuakeML to NonLinLoc PHS" tile
    And I should see the "NDC Semantic frontend" tile
    And I should see the text "This is the frontend for the National Data Catalog for Semantic Interoperability." in the "NDC Semantic frontend" tile
    And I should see the "Software Registry" tile
    And I should see the "Immuni" tile

    # Check facets headings.
    And I see the following headings:
      | Category           |
      | Development status |
      | Source catalogues  |

    # Check facets.
    And the "OSS category" checkbox facet should allow selecting the following values "application-development (1),content-management (1),data-analytics (1),data-collection (2),digital-asset-management (2),it-development (1)"
    And the "OSS development status" checkbox facet should allow selecting the following values "Beta (1),Development (1),Obsolete (1),Stable (2)"
    And the "OSS catalog" checkbox facet should allow selecting the following values "Developers Italia (5)"
    And the page should contain the html text "Italian National OSS Catalogue, by https://developers.italia.it website."

    # Check facet filtering.
    Then I click "application-development (1)"
    When I should see the text "SEARCH RESULTS (1)"
    And I should not see the "Django-MDQ" tile
    And I should not see the "INGV - QuakeML to NonLinLoc PHS" tile
    And I should not see the "NDC Semantic frontend" tile
    And I should see the "Software Registry" tile
    And I should not see the "Immuni" tile

    # Check OSS label in tile footer.
    And I should see the text "OSS Solution" in the "Software Registry" tile

    # Check input filtering.
    Then I press "Clear filters"
    And I fill in "Search catalogue" with "Immuni"
    And I press "Search OSS catalogue"
    When I should see the text "SEARCH RESULTS (1)"
    And I should not see the "Django-MDQ" tile
    And I should not see the "INGV - QuakeML to NonLinLoc PHS" tile
    And I should not see the "NDC Semantic frontend" tile
    And I should not see the "Software Registry" tile
    And I should see the "Immuni" tile

    # Check header count of solutions.
    Given I delete the "NDC Semantic frontend" oss_solution
    When I visit the OSS search page
    And I see the text "4 Open Source Software Solutions" in the "Header" region

    When set status with 0 in "Django-MDQ" oss_solution
    Then I visit the OSS search page
    And I see the text "3 Open Source Software Solutions" in the "Header" region

    When set status with 1 in "Django-MDQ" oss_solution
    Then I visit the OSS search page
    And I see the text "4 Open Source Software Solutions" in the "Header" region

    Then I go to the homepage of the "EU Open Source Solutions Catalogue" collection
    And I should not see the "Entity actions" region

    When I am logged in as a facilitator of the "EU Open Source Solutions Catalogue" collection
    And I go to the homepage of the "EU Open Source Solutions Catalogue" collection
    And I should see the link "Metadata" in the "Entity actions" region

    When I am logged in as a user with the "moderator" role
    And I go to the homepage of the "EU Open Source Solutions Catalogue" collection
    And I should see the link "Metadata" in the "Entity actions" region

    When I am logged in as an "authenticated user"
    And I go to the homepage of the "EU Open Source Solutions Catalogue" collection
    And I should not see the "Entity actions" region

  @javascript
  Scenario: User is able to use autocomplete.
    Given oss_solution content:
      | title                     | oss_development_status | oss_source        | oss_categories                                                  | oss_short_description                                                                                                                         | status    |
      | Trembling Mage            | stable                 | developers_italia | content-management                                              | A lightweight SAML2 MDQ server that implements draft-young-md-query implementation.                                                           | published |
      | The Missing Secrets       | stable                 | developers_italia | data-analytics,data-collection                                  | Docker image to convert QuakeML format to NonLinLoc PHS format.                                                                               | published |
      | Light of Person           | beta                   | developers_italia | digital-asset-management                                        |                                                                                                                                               | published |
      | The Word's Game           | development            | developers_italia | application-development,it-development,digital-asset-management | Software Registry makes software development phase simplier by showing extended information about projects and allowing source code searches. | published |
      | The Snake of the Servant  | obsolete               | developers_italia | data-collection                                                 | Immuni is a mobile app that helps us to fight the COVID-19 pandemic by notifying users at risk of carrying the virus as early as possible     | published |

    When I visit the OSS search page

    Then I enter "nd" in the "oss search bar"
    And I wait for AJAX to finish
    Then I should see the following "oss search" suggestions:
      | Trembling Mage      |
      | The Missing Secrets |

    Then I enter "Mage" in the "oss search bar"
    And I wait for AJAX to finish
    Then I should see the following "oss search" suggestions:
      | Trembling Mage |

    Then I enter "Game" in the "oss search bar"
    And I wait for AJAX to finish
    Then I should see the following "oss search" suggestions:
      | The Word's Game |
