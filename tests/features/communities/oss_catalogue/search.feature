@api @group_e
Feature: OSS catalogue search
  As a user of the site I can find solutions through the OSS catalogue search.

  Scenario: User can view the OSS catalogue search page.
    Then I visit the OSS search page
    When I should see the heading "Search in EU Open Source Solutions Catalogue"
    And the following fields should be present "Sort by:,Start typing to search…"
    And I should see the text "No content found for your search."
    And I should see the button "Clear filters"

  Scenario: Archived solution is not displayed in search results
    Given oss_solution content:
      | title   | oss_development_status | oss_source        | status    | oss_archived |
      | Linux   | stable                 | developers_italia | published | 1            |
      | macOS   | development            | developers_italia | published | 0            |
      | Android | obsolete               | developers_italia | published |              |

    When I visit the OSS search page
    Then I should see the text "SEARCH RESULTS (2)"
    And I should not see the "Linux" tile

  Scenario: User is able to search.
    # Mimic the "EU OSSC" collection.
    Given collection:
      | uri   | http://data.europa.eu/w21/6d621a47-71ce-4f1a-90e2-8859403d3ecd |
      | title | EU Open Source Solutions Catalogue                             |
      | state | published                                                      |

    When I visit the OSS search page
    Then I see the text "EU Open Source Solutions Catalogue" in the "Header" region
    And I see the text "Solutions created by and for European Public Services" in the "Header" region

    Given oss_solution content:
      | title                           | oss_development_status | oss_source        | oss_categories                                                  | oss_short_description                                                                                                                         | oss_git_description                                                               | status    | oss_release_date | oss_logo                                      | oss_software_version |
      | Django-MDQ                      | stable                 | developers_italia | content-management                                              | A lightweight SAML2 MDQ server that implements draft-young-md-query implementation.                                                           |                                                                                   | published | 2022-04-02       | https://placehold.co/200x200.png              | 1.0-beta             |
      | INGV - QuakeML to NonLinLoc PHS | stable                 | developers_italia | data-analytics,data-collection                                  | Docker image to convert QuakeML format to NonLinLoc PHS format.                                                                               |                                                                                   | published | 2000-07-13       |                                               |                      |
      | NDC Semantic frontend           | beta                   | developers_italia | digital-asset-management                                        |                                                                                                                                               | This is the frontend for the National Data Catalog for Semantic Interoperability. | published | 2023-08-13       | https://placehold.co/200x200/orange/white.png | 2.2.4                |
      | Software Registry               | development            | developers_italia | application-development,it-development,digital-asset-management | Software Registry makes software development phase simplier by showing extended information about projects and allowing source code searches. |                                                                                   | published | 2000-04-13       | https://placehold.co/200x200/red/white.png    | 0.1-alpha            |
      | Immuni                          | obsolete               | developers_italia | data-collection                                                 | Immuni is a mobile app that helps us to fight the COVID-19 pandemic by notifying users at risk of carrying the virus as early as possible     |                                                                                   | published |                  |                                               |                      |

    Then I visit the OSS search page

    # Check main area
    And I should see the text "SEARCH RESULTS (5)"

    # Check tiles.
    And I should see the "Django-MDQ" tile
    And I should see the text "Release date:" in the "Django-MDQ" tile
    And I should see the text "02/04/2022" in the "Django-MDQ" tile
    And I should see the text "1.0-beta" in the "Django-MDQ" tile
    And I should see the image "c3e7768d02acad9995a74bb403cfaeae.png.webp"
    And I should see the "INGV - QuakeML to NonLinLoc PHS" tile
    And I should see the text "Docker image to convert QuakeML format to NonLinLoc PHS format." in the "INGV - QuakeML to NonLinLoc PHS" tile
    And I should see the "NDC Semantic frontend" tile
    And I should see the text "2.2.4" in the "NDC Semantic frontend" tile
    And I should see the text "This is the frontend for the National Data Catalog for Semantic Interoperability." in the "NDC Semantic frontend" tile
    And I should see the image "fa4dc89f4a3cd8948cca2aac974f3e15.png.webp"
    And I should see the "Software Registry" tile
    And I should see the text "0.1-alpha" in the "Software Registry" tile
    And I should see the image "2875501db3e1dc4a54df527a59964d40.png.webp"
    And I should see the "Immuni" tile
    And I should not see the text "Release date:" in the "Immuni" tile

    # Check facets headings.
    And I see the following headings:
      | Category           |
      | Development status |
      | Source catalogues  |

    # Check facets.
    And the "OSS category" checkbox facet should allow selecting the following values "application-development (1),content-management (1),data-analytics (1),data-collection (2),digital-asset-management (2),it-development (1)"
    And the "OSS development status" checkbox facet should allow selecting the following values "Beta (1),Development (1),Obsolete (1),Stable (2)"
    And the "OSS catalog" checkbox facet should allow selecting the following values "Developers Italia (5)"
    And I should see the "li" element with the "title" attribute set to "Italian National OSS Catalogue, by https://developers.italia.it website." in the "Search filters" region
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

  Scenario: User is able to search by string in any language.
    Given oss_solution multilingual content:
      | title         | status    | changed    | oss_source        | oss_release_date | langcode | default_translation | oss_features                                                                                  | oss_short_description                                                                                                                                        | oss_long_description                                                                                                                                                                                                                        | oss_publiccode_yml_content |
      | MongoDB Atlas | published | 1700000000 | developers_italia | 2025-10-19       | en       | 1                   | Strong security measures, Easier scalability, Extensive analytics and monitoring              | The multi-cloud database service at the heart of our developer data platform that accelerates and simplifies how you build with data.                        | Atlas simplifies deploying and managing your databases while offering the versatility you need to build resilient and performant global applications on the cloud providers of your choice.                                                 | yml file                   |
      |               | ~~~       | ~~~        | ~~~               | ~~~              | de       | 0                   | Starke Sicherheitsmaßnahmen, einfachere Skalierbarkeit, umfangreiche Analysen und Überwachung | Der Multi-Cloud-Datenbankdienst im Herzen unserer Entwicklerdatenplattform, der die Erstellung mit Daten beschleunigt und vereinfacht.                       | Atlas vereinfacht die Bereitstellung und Verwaltung Ihrer Datenbanken und bietet gleichzeitig die Vielseitigkeit, die Sie zum Erstellen belastbarer und leistungsstarker globaler Anwendungen bei den Cloud-Anbietern Ihrer Wahl benötigen. | ~~~                        |
      |               | ~~~       | ~~~        | ~~~               | ~~~              | it       | 0                   | Forti misure di sicurezza, scalabilità più semplice, analisi e monitoraggio estesi            | Il servizio di database multi-cloud al centro della nostra piattaforma dati per sviluppatori che accelera e semplifica il modo in cui costruisci con i dati. | Atlas semplifica la distribuzione e la gestione dei tuoi database offrendo al contempo la versatilità necessaria per creare applicazioni globali resilienti e performanti sui provider cloud di tua scelta.                                 | ~~~                        |

    # Search by oss_features.
    Then I visit the OSS search page
    And I fill in "Search catalogue" with "measures"
    And I press "Search OSS catalogue"
    When I should see the "MongoDB Atlas" tile

    Then I fill in "Search catalogue" with "Sicherheitsmaßnahmen"
    And I press "Search OSS catalogue"
    When I should see the "MongoDB Atlas" tile

    Then I fill in "Search catalogue" with "scalabilità"
    And I press "Search OSS catalogue"
    When I should see the "MongoDB Atlas" tile

    # Search by oss_short_description.
    Then I fill in "Search catalogue" with "accelerates"
    And I press "Search OSS catalogue"
    When I should see the "MongoDB Atlas" tile

    Then I fill in "Search catalogue" with "Multi-Cloud-Datenbankdienst"
    And I press "Search OSS catalogue"
    When I should see the "MongoDB Atlas" tile

    Then I fill in "Search catalogue" with "piattaforma"
    And I press "Search OSS catalogue"
    When I should see the "MongoDB Atlas" tile

    # Search by oss_long_description.
    Then I fill in "Search catalogue" with "versatility"
    And I press "Search OSS catalogue"
    When I should see the "MongoDB Atlas" tile

    Then I fill in "Search catalogue" with "Vielseitigkeit"
    And I press "Search OSS catalogue"
    When I should see the "MongoDB Atlas" tile

    Then I fill in "Search catalogue" with "distribuzione"
    And I press "Search OSS catalogue"
    When I should see the "MongoDB Atlas" tile

  @javascript
  Scenario: User is able to use autocomplete.
    Given oss_solution content:
      | title                     | oss_development_status | oss_source        | oss_categories                                                   | oss_short_description                                                                 | status    | oss_release_date | oss_vitality_index |
      | Another Open Source App   | alpha                  | developers_italia | data-analytics,data-collection                                   | A new open-source application that simplifies data management and analysis.           | published | 2023-01-15       | 0.56               |
      | Magic Data Converter      | stable                 | developers_italia | application-development,it-development                           | A reliable tool to convert various data formats into more usable ones.                | published | 2022-11-30       | 0.51               |
      | Creative Content Creator  | development            | developers_italia | application-development,it-development,digital-asset-management  | An exciting project for creating unique and stunning digital content.                 | published |                  | 0.98               |
      | Data Security Sentinel    | stable                 | developers_italia | content-management                                               | Enhance your data security with this robust and well-maintained application.          | published | 2022-09-12       | 0.67               |
      | Smart IoT Manager         | beta                   | developers_italia | it-development                                                   | An IoT management system that helps you control and monitor your devices smartly.     | published | 2021-08-20       | 0.78               |
      | Open AI Helper            | development            | developers_italia | data-analytics,data-collection                                   | An AI-powered assistant to make your tasks easier and more efficient.                 | published |                  | 0.50               |
      | Community Data Tracker    | stable                 | developers_italia | application-development,it-development                           | Track and analyze community data with this reliable tool.                             | published | 2022-12-10       | 0.99               |
      | Digital Artistry Studio   | beta                   | developers_italia | application-development,it-development,digital-asset-management  | Express your creativity with powerful digital art tools.                              | published | 2021-07-05       | 0.76               |
      | Secure Password Manager   | stable                 | developers_italia | content-management                                               | Manage your passwords securely and keep your data safe with this trusted application. | published | 2022-06-18       | 0.69               |
      | Health Tracker Pro        | development            | developers_italia | it-development                                                   | An upcoming health and fitness app to help you stay on top of your well-being goals.  | published |                  | 0.55               |
      | LibreOffice               | development            | developers_italia | it-development                                                   | LibreOffice is a free and powerful office suite, and a successor to OpenOffice.       | published | 2023-06-15       | 0.88               |

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
    Then I enter "ope" in the "oss search bar"
    And I wait for AJAX to finish
    Then I should see the following "oss search" suggestions:
      | Another Open Source App   |
      | Open AI Helper            |
    Then I enter "open" in the "oss search bar"
    And I wait for AJAX to finish
    Then I should see the following "oss search" suggestions:
      | Another Open Source App   |
      | Open AI Helper            |
    Then I enter "lib" in the "oss search bar"
    And I wait for AJAX to finish
    Then I should see the following "oss search" suggestions:
      | LibreOffice              |
    Then I enter "libre" in the "oss search bar"
    And I wait for AJAX to finish
    Then I should see the following "oss search" suggestions:
      | LibreOffice              |

    # Check sort by filters.
    When I visit the OSS search page
    Then the option "Most Recent" should be selected
    And I should see the text "SEARCH RESULTS (11)"
    And I should see the following tiles in the correct order:
      | LibreOffice              |
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
    Then I should not see the error message "The submitted value orderaz in the Sort by: element is not allowed."
    And I should see the following tiles in the correct order:
      | Another Open Source App  |
      | Community Data Tracker   |
      | Creative Content Creator |
      | Data Security Sentinel   |
      | Digital Artistry Studio  |
      | Health Tracker Pro       |
      | LibreOffice              |
      | Magic Data Converter     |
      | Open AI Helper           |
      | Secure Password Manager  |
      | Smart IoT Manager        |
    And I should be on "/eu-oss-catalogue/solutions?oss_keys=&sort_by=orderaz"

    Given I select "Alphabetic Order Z-A" from "Sort by"
    Then I should not see the error message "The submitted value orderza in the Sort by: element is not allowed."
    And I should see the following tiles in the correct order:
      | Smart IoT Manager        |
      | Secure Password Manager  |
      | Open AI Helper           |
      | Magic Data Converter     |
      | LibreOffice              |
      | Health Tracker Pro       |
      | Digital Artistry Studio  |
      | Data Security Sentinel   |
      | Creative Content Creator |
      | Community Data Tracker   |
      | Another Open Source App  |
    And I should be on "/eu-oss-catalogue/solutions?oss_keys=&sort_by=orderza"

    Given I select "Most Active" from "Sort by"
    Then I should not see the error message "The submitted value vitality in the Sort by: element is not allowed."
    And I should see the following tiles in the correct order:
      | Community Data Tracker   |
      | Creative Content Creator |
      | LibreOffice              |
      | Smart IoT Manager        |
      | Digital Artistry Studio  |
      | Secure Password Manager  |
      | Data Security Sentinel   |
      | Another Open Source App  |
      | Health Tracker Pro       |
      | Magic Data Converter     |
      | Open AI Helper           |
    And I should be on "/eu-oss-catalogue/solutions?oss_keys=&sort_by=vitality"
