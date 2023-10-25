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

    # Check facets.
    And the "OSS category" checkbox facet should allow selecting the following values "application-development (1),content-management (1),data-analytics (1),data-collection (2),digital-asset-management (2),it-development (1)"
    And the "OSS development status" checkbox facet should allow selecting the following values "Beta (1),Development (1),Obsolete (1),Stable (2)"
    And the "OSS catalog" checkbox facet should allow selecting the following values "Developers Italia"
    And the page should contain the html text "Italian National OSS Catalogue, by https://developers.italia.it website."

    # Check facet filtering.
    Then I click "application-development (1)"
    When I should see the text "SEARCH RESULTS (1)"
    And I should not see the "Django-MDQ" tile
    And I should not see the "INGV - QuakeML to NonLinLoc PHS" tile
    And I should not see the "NDC Semantic frontend" tile
    And I should see the "Software Registry" tile
    And I should not see the "Immuni" tile

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

  @javascript
  Scenario: User is able to use autocomplete.
    Given oss_solution content:
      | title                     | oss_development_status | oss_source        | oss_categories                                                   | oss_short_description                                                                 | status    | oss_release_date |
      | Another Open Source App   | alpha                  | developers_italia | data-analytics,data-collection                                   | A new open-source application that simplifies data management and analysis.           | published | 2023-01-15       |
      | Magic Data Converter      | stable                 | developers_italia | application-development,it-development                           | A reliable tool to convert various data formats into more usable ones.                | published | 2022-11-30       |
      | Creative Content Creator  | development            | developers_italia | application-development,it-development,digital-asset-management  | An exciting project for creating unique and stunning digital content.                 | published |                  |
      | Data Security Sentinel    | stable                 | developers_italia | content-management                                               | Enhance your data security with this robust and well-maintained application.          | published | 2022-09-12       |
      | Smart IoT Manager         | beta                   | developers_italia | it-development                                                   | An IoT management system that helps you control and monitor your devices smartly.     | published | 2021-08-20       |
      | Open AI Helper            | development            | developers_italia | data-analytics,data-collection                                   | An AI-powered assistant to make your tasks easier and more efficient.                 | published |                  |
      | Community Data Tracker    | stable                 | developers_italia | application-development,it-development                           | Track and analyze community data with this reliable tool.                             | published | 2022-12-10       |
      | Digital Artistry Studio   | beta                   | developers_italia | application-development,it-development,digital-asset-management  | Express your creativity with powerful digital art tools.                              | published | 2021-07-05       |
      | Secure Password Manager   | stable                 | developers_italia | content-management                                               | Manage your passwords securely and keep your data safe with this trusted application. | published | 2022-06-18       |
      | Health Tracker Pro        | development            | developers_italia | it-development                                                   | An upcoming health and fitness app to help you stay on top of your well-being goals.  | published |                  |

    When I visit the OSS search page

    Then I enter "app" in the "oss search bar"
    And I wait for AJAX to finish
    Then I should see the following "oss search" suggestions:
      | Another Open Source App  |
    Then I enter "data" in the "oss search bar"
    And I wait for AJAX to finish
    Then I should see the following "oss search" suggestions:
      | Community Data Tracker   |
      | Magic Data Converter     |
      | Data Security Sentinel   |
    Then I enter "open" in the "oss search bar"
    And I wait for AJAX to finish
    Then I should see the following "oss search" suggestions:
      | Another Open Source App   |
      | Open AI Helper            |

    # Check sort by filters.
    When I visit the OSS search page
    Then the option "Most Recent" should be selected
    And I should see the text "SEARCH RESULTS (10)"
    And I should see the following tiles in the correct order:
      | Another Open Source App  |
      | Community Data Tracker   |
      | Magic Data Converter     |
      | Data Security Sentinel   |
      | Secure Password Manager  |
      | Smart IoT Manager        |
      | Digital Artistry Studio  |
      | Creative Content Creator |
      | Open AI Helper           |
      | Health Tracker Pro       |
    And I should be on "/eu-oss-catalogue/solutions?oss_keys=&sort_by=changed"

    Given I select "Alphabetic Order A-Z" from "Sort by"
    Then I should see the following tiles in the correct order:
      | Another Open Source App  |
      | Community Data Tracker   |
      | Creative Content Creator |
      | Data Security Sentinel   |
      | Digital Artistry Studio  |
      | Health Tracker Pro       |
      | Magic Data Converter     |
      | Open AI Helper           |
      | Secure Password Manager  |
      | Smart IoT Manager        |
    And I should be on "/eu-oss-catalogue/solutions?oss_keys=&sort_by=orderaz"

    Given I select "Alphabetic Order Z-A" from "Sort by"
    Then I should see the following tiles in the correct order:
      | Smart IoT Manager        |
      | Secure Password Manager  |
      | Open AI Helper           |
      | Magic Data Converter     |
      | Health Tracker Pro       |
      | Digital Artistry Studio  |
      | Data Security Sentinel   |
      | Creative Content Creator |
      | Community Data Tracker   |
      | Another Open Source App  |
    And I should be on "/eu-oss-catalogue/solutions?oss_keys=&sort_by=orderza"
