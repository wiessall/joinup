@api @group-clone
Feature:
  In order to present the details about EU OSS catalogue
  I want to view a dedicated collection and landing page

  Scenario: User sees the right content
    Given oss_solution content:
      | title              | oss_development_status | oss_source        | oss_categories                                                  | oss_short_description                                                                                                                         | status      | oss_archived | oss_release_date | oss_vitality_index |
      | Not published      | development            | developers_italia | content-management                                              | Never published                                                                                                                               | unpublished |              | 2022-05-24       | 0.56               |
      | Not released       | development            | developers_italia | content-management                                              | Never released                                                                                                                                | published   |              |                  | 0.51               |
      | Archived           | stable                 | developers_italia | content-management                                              | A lightweight SAML2 MDQ server that implements draft-young-md-query implementation.                                                           | published   | 1            | 2022-01-13       | 0.98               |
      | C4S ERP            | stable                 | developers_italia | content-management                                              | A lightweight SAML2 MDQ server that implements draft-young-md-query implementation.                                                           | published   | 0            | 2022-04-13       | 0.98               |
      | Vito Schiraldi     | stable                 | developers_italia | data-analytics,data-collection                                  | Docker image to convert QuakeML format to NonLinLoc PHS format.                                                                               | published   | 0            | 2000-07-13       | 0.67               |
      | Spunta             | beta                   | developers_italia | digital-asset-management                                        |                                                                                                                                               | published   |              | 2023-08-13       | 0.78               |
      | Semantic Turkey    | development            | developers_italia | application-development,it-development,digital-asset-management | Software Registry makes software development phase simplier by showing extended information about projects and allowing source code searches. | published   |              | 2000-04-13       | 0.50               |
      | Cristian Gagliardi | obsolete               | developers_italia | data-collection                                                 | Immuni is a mobile app that helps us to fight the COVID-19 pandemic by notifying users at risk of carrying the virus as early as possible     | published   |              | 2020-09-13       | 0.99               |

    Given I visit "/collection/eu-oss-catalogue"

    # User is redirected from the standard collection URL.
    Then I should be on "/eu-oss-catalogue"
    And I should not see the link "Report abusive content"
    And I see the text "Solutions created by and for European Public Services" in the "Header" region

    # Check menus.
    Then I should not see the link "Overview" in the "Navigation menu"
    And I should not see the link "Members" in the "Navigation menu"
    And I should not see the link "About" in the "Navigation menu"
    But I should see the link "Welcome" in the "Navigation menu"
    And I should see the link "Search" in the "Navigation menu"
    And I should not see the "Entity actions" region

    And I should see the following headings:
      | Most recent solutions                             |
      | Most active solutions                             |
      | Do you want to publish your open source solution? |
      | Let’s start with your software name               |
      | Frequently Asked Questions (FAQ)                  |
      | Do you need any help?                             |
      | Follow our journey on the social media!           |
    And I should see the text "Discover our European Open Source Software"
    And I should see the text "Repository of the Open Source Software Solutions of the Public Administrations"
    And I should see the text "Centralized platform to discover Open Source Software Solutions for diverse needs"
    And I should see the text "Open Source Software Solutions that empower collaboration, creativity, and efficiency across various domains"
    And I should see the link "Browse the catalogue"
    And I should see the text "Just follow the guide!"
    And I should see the text "EU Open Source Solutions Catalogue"
    And I should not see the link "Report abusive content"

    # Most recent solutions carousel.
    Then I should see the following tiles in the "Most recent solutions" region:
      | Spunta             |
      | C4S ERP            |
      | Cristian Gagliardi |
      | Vito Schiraldi     |
      | Semantic Turkey    |
      | Not released       |

    When I click "View all" in the "Most recent solutions" region
    Then the option with text "Most Recent" from select "Sort by" is selected
    And I should see the following tiles in the correct order:
      | Spunta             |
      | C4S ERP            |
      | Cristian Gagliardi |
      | Vito Schiraldi     |
      | Semantic Turkey    |
      | Not released       |

    # Most active solutions carousel.
    When I visit the OSS welcome page
    Then I should see the following tiles in the "Most active solutions" region:
      | Cristian Gagliardi |
      | C4S ERP            |
      | Spunta             |
      | Vito Schiraldi     |
      | Not released       |
      | Semantic Turkey    |

    When I click "View all" in the "Most active solutions" region
    Then the option with text "Most Active" from select "Sort by" is selected
    And I should see the following tiles in the correct order:
      | Cristian Gagliardi |
      | C4S ERP            |
      | Spunta             |
      | Vito Schiraldi     |
      | Not released       |
      | Semantic Turkey    |

    # Check OSS label in tile footer.
    When I visit the OSS welcome page
    Then I should see the text "OSS Solution" in the "Spunta" tile

    When I should not see the link "advanced search"
    Then I click "Browse the catalogue"
    And I should see the heading "Search in EU Open Source Solutions Catalogue"

  Scenario: User sees short description on OSS Solution page.
    Given oss_solution content:
      | title | oss_development_status | oss_source        | oss_categories | oss_short_description   | oss_git_description   | status    | oss_release_date |
      | Foo   | development            | developers_italia | data-analytics | Foo's short description | Foo's GIT description | published | 2014-11-19       |
    When I go to the content page of the type "oss_solution" with the title "Foo"
    Then I should see the text "Foo's short description"
    But I should not see the text "Foo's GIT description"

  Scenario: User can see the OSS header.
    Given oss_solution content:
      | title   | oss_development_status | oss_source        | oss_categories | oss_short_description   | oss_git_description   | status    | oss_release_date |
      | Olympus | development            | developers_italia | data-analytics | Foo's short description | Foo's GIT description | published | 2014-11-19       |
    When I go to the content page of the type "oss_solution" with the title "Olympus"
    Then I see the text "EU Open Source Solutions Catalogue" in the "Header" region
    And I see the text "Solutions created by and for European Public Services" in the "Header" region
    And I should see the following links in the "Navigation menu" region:
      | Welcome |
      | Search  |

  Scenario: User can see GIT repo indicators.
    Given oss_solution content:
      | title              | oss_source        | oss_git_stars | oss_git_open_issues | oss_git_open_pull_requests | oss_git_forks | status    |
      | Olympus indicators | developers_italia | 1111          | 2222                | 3333                       | 4444          | published |
    When I go to the content page of the type "oss_solution" with the title "Olympus indicators"
    Then I should see the following lines of text:
      | 1111 |
      | 2222 |
      | 3333 |
      | 4444 |

  Scenario: User sees GIT description on OSS Solution page if short description is missing.
    Given oss_solution content:
      | title | oss_development_status | oss_source        | oss_categories | oss_short_description | oss_git_description   | status    | oss_release_date |
      | Foo   | stable                 | developers_italia | data-analytics |                       | Foo's GIT description | published | 2014-11-19       |
    When I go to the content page of the type "oss_solution" with the title "Foo"
    Then I should see the text "Foo's GIT description"

  @loggedErrors
  Scenario: All necessary OSS Solution fields are visible on node page.
    Given oss_contact content:
      | title           |
      | FOSS Conference |
    Given oss_contractor content:
      | title     |
      | ACME Ltd. |
    Given oss_used_by terms:
      | name             |
      | Administration 1 |
      | Administration 2 |
      | Administration 3 |
    Given oss_platform terms:
      | name  |
      | macOs |
    Given oss_solution content:
      | title                  | oss_available_languages | oss_development_status | oss_source        | oss_categories                    | oss_long_description                                                                             | oss_short_description | oss_git_description | status    | oss_release_date | oss_documentation   | oss_features         | oss_git_url         | oss_landing_url     | oss_license      | oss_logo                         | oss_maintenance_contacts | oss_maintenance_contractors | oss_maintenance_type | oss_owner       | oss_platforms | oss_publiccode_yml_version | oss_scope                       | oss_screenshots                                                                                    | oss_software_type  | oss_software_version | oss_vitality_index | oss_used_by                                        |
      | Super Awesome Software | it,en,de,error,pt       | stable                 | developers_italia | document-management,collaboration | The long description. A patient developer can make this long enough to be at least 5 lines long. | The short description | The GIT description | published | 2014-11-19       | https://example.com | Feature A, Feature B | https://example.com | https://example.com | GPL-2.0-or-later | https://placehold.co/200x200.png | FOSS Conference          | ACME Ltd.                   | community            | FOSS Conference | macOs         | 0.4                        | science-and-technology,security | https://placehold.co/600x400.png,https://placehold.co/400x400.png,https://placehold.co/800x400.jpg | standalone/desktop | 3.17.143             | 0.41               | Administration 1,Administration 2,Administration 3 |
    When I go to the content page of the type "oss_solution" with the title "Super Awesome Software"
    Then I should be on "/eu-oss-catalogue/solutions/super-awesome-software"
    And the site should not have log records with level lower than "notice"
    And I should see the following headings:
      | Super Awesome Software |
      | Description            |
      | Features               |
      | Intended audience      |
      | Detailed information   |
      | Gallery                |
    And I should see the following lines of text:
      | Source catalogue                                                                                   |
      | The short description                                                                              |
      | Description                                                                                        |
      |   The long description. A patient developer can make this long enough to be at least 5 lines long. |
      | Copyrights owner                                                                                   |
      |   FOSS Conference                                                                                  |
      | Features                                                                                           |
      |   Feature A                                                                                        |
      |   Feature B                                                                                        |
      | Supported languages                                                                                |
      |   Italian,                                                                                         |
      |   English,                                                                                         |
      |   German,                                                                                          |
      |   error,                                                                                           |
      |   Portuguese, International                                                                        |
      | Development status                                                                                 |
      |   Stable                                                                                           |
      | License                                                                                            |
      |   GPL-2.0-or-later                                                                                 |
      | Platforms                                                                                          |
      |   macOs                                                                                            |
      | Maintenance type                                                                                   |
      |   Maintained by people donating their time to the project                                          |
      | Software type                                                                                      |
      |   standalone/desktop                                                                               |
      | Used by                                                                                            |
      |   Administration 1,                                                                                |
      |   Administration 2,                                                                                |
      |   Administration 3                                                                                 |
      | Technical Maintainers                                                                              |
      |   ACME Ltd.                                                                                        |
      | Technical Contacts                                                                                 |
      |   FOSS Conference                                                                                  |
      | Vitality                                                                                           |
      | 41%                                                                                                |
    And the "div" element with "title" attribute should contain "The software is typically installed and run in a a desktop operating system environment."
    And I should see the following links:
      # Categories.
      | document-management              |
      | collaboration                    |
      # Providers.
      | Developers Italia                |
      # Links to resources.
      | Source code repository           |
      | Project’s website                |
      | Documentation                    |
      # Audience
      | science-and-technology           |
      | security                         |
    And I should not see the following links:
      | Show all 3 administrations |
      | Show administration        |
      # We should see the actual images instead.
      | https://placehold.co/600x400.png |
      | https://placehold.co/400x400.png |
      | https://placehold.co/800x400.jpg |
    But I should have the following images on the page in this order
      # Downloaded instance of https://placehold.co/600x400.png
      | 99ccd804281fede2d4e3dcc3a9b30a4c.png |
      # Downloaded instance of https://placehold.co/400x400.png.
      | 93ac8cf04f904a4f19a8418fd565a448.png |
      # Downloaded instance of https://placehold.co/800x400.jpg.
      | aeea83704c583614d4900c69de40802c.jpg |
    # Clicking links of Categories, Providers or Audience must navigate to
    # search.
    When I click "document-management"
    Then I should be on "/eu-oss-catalogue/solutions"
    And I should see the following facet summary "document-management"
    And the link "Search" is in the active trail
    When I go to the content page of the type "oss_solution" with the title "Super Awesome Software"
    And I click "Developers Italia"
    Then I should be on "/eu-oss-catalogue/solutions"
    And I should see the following facet summary "Developers Italia"
    When I go to the content page of the type "oss_solution" with the title "Super Awesome Software"
    And I click "science-and-technology"
    Then I should be on "/eu-oss-catalogue/solutions"
    And the link "Search" is in the active trail
    And I should see the following facet summary "science-and-technology"

    # Check that this node type doesn't leak in search as facet.
    When I visit "/search"
    Then I should not see the link "Open source software solution"

  Scenario Outline: OSS Catalogue's inaccessible routes.
    Given I am logged in as a user with the "<role>" role
    And I accept the "Legal notice" agreement
    When I go to "<path>"
    Then I should get an access denied error

    Examples:
      | path                     | role          |
      | eu-oss-catalogue/members | administrator |
      | eu-oss-catalogue/members | authenticated |
      | eu-oss-catalogue/members | moderator     |

  Scenario: Anonymous user cannot access OSS Catalogue's inaccessible routes.
    Given I am not logged in
    When I go to "eu-oss-catalogue/members"
    Then I should see the heading "Sign in to continue"

  @javascript
  Scenario: Modal link should be displayed and must show all field values.
    Given oss_used_by terms:
      | name             |
      | Administration 1 |
      | Administration 2 |
      | Administration 3 |
      | Administration 4 |
      | Administration 5 |
      | Administration 6 |
      | Administration 7 |
    And oss_solution content:
      | title      | status    | oss_release_date | oss_available_languages                | oss_used_by                                                                                                            |
      | With modal | published | 2014-11-19       | it,en,de,pt,eo,fr,he,ja,lt,pl,ro,gd,si | Administration 1,Administration 2,Administration 3,Administration 4,Administration 5,Administration 6,Administration 7 |

    When I go to the content page of the type "oss_solution" with the title "With modal"

    # Check "Used by" field.
    Then I should see the following lines of text:
      | Used by           |
      | Administration 1, |
      | Administration 2, |
      | Administration 3, |
      | Administration 4, |
      | Administration 5  |
    But I should not see the following lines of text:
      | Administration 6 |
      | Administration 7 |
    And I should not see the link "Show administration"
    But I should see the link "Show all 7 administrations"
    When I click "Show all 7 administrations"
    Then a modal should open
    And I should see the following lines of text:
      | 7 administrations are currently using the With modal solution: |
      | Administration 1                                               |
      | Administration 2                                               |
      | Administration 3                                               |
      | Administration 4                                               |
      | Administration 5                                               |
      | Administration 6                                               |
      | Administration 7                                               |
    When I click on element ".ui-dialog .ui-dialog-titlebar-close"
    Then the modal should be closed
    And I should not see the following lines of text:
      | Administration 6 |
      | Administration 7 |

    # Check "Supported languages" field.
    Then I should see the following lines of text:
      | Supported languages |
      | Italian,            |
      | English,            |
    And I should see the link "Show all 13 languages"
    But I should not see the following lines of text:
      | Romanian,     |
      | Scots Gaelic, |
    When I click "Show all 13 languages"
    Then a modal should open
    And I should see the following lines of text:
      | Italian      |
      | English      |
      | Romanian     |
      | Scots Gaelic |
    When I click on element ".ui-dialog .ui-dialog-titlebar-close"
    Then the modal should be closed
    And I should not see the following lines of text:
      | Romanian,     |
      | Scots Gaelic, |

  @javascript
  Scenario: Screenshot carousel with 4 items works as expected.
    Given oss_solution content:
      | title | oss_screenshots                                                                                    | oss_development_status | oss_source        | oss_categories   | status    | oss_release_date |
      | Four  | https://placehold.co/800x400.jpg,https://placehold.co/500x400.png,https://placehold.co/700x400.png,https://placehold.co/600x400.png | stable                 | developers_italia | data-analytics   | published | 2014-11-19       |
    When I go to the content page of the type "oss_solution" with the title "Four"
    Then I should see the text "Gallery"
    And I should have the following images on the page in this order
      # Downloaded instance of https://placehold.co/800x400.jpg.
      | aeea83704c583614d4900c69de40802c.jpg |
      # Downloaded instance of https://placehold.co/500x400.png.
      | b68f93a5aca1d09eb50e9ea901b90d7c.png |
      # Downloaded instance of https://placehold.co/700x400.png
      | ef8676b28d34d82c4a0688a35ec76335.png |
      # Downloaded instance of https://placehold.co/600x400.png
      | 99ccd804281fede2d4e3dcc3a9b30a4c.png |
    # Assuming 1600px wide browser window.
    And I should see the following images on the page
      # First 3 images must be visible.
      | aeea83704c583614d4900c69de40802c.jpg |
      | b68f93a5aca1d09eb50e9ea901b90d7c.png |
      | ef8676b28d34d82c4a0688a35ec76335.png |
    # Fourth must not be visible yet.
    And I should not see the image "99ccd804281fede2d4e3dcc3a9b30a4c.png"
    Then I click on element ".slick--optionset--oss-solution-screenshot button[aria-label='Next']:not([aria-disabled='true'])"
    And I wait up to "5" seconds for CSS transitions to finish
    And I should see the image "99ccd804281fede2d4e3dcc3a9b30a4c.png"
    Then I click on element ".slick--optionset--oss-solution-screenshot button[aria-label='Previous']:not([aria-disabled='true'])"
    And I wait up to "5" seconds for CSS transitions to finish
    And I should not see the image "99ccd804281fede2d4e3dcc3a9b30a4c.png"
    And the page should have an element with CSS selector ".slick--optionset--oss-solution-screenshot button[aria-label='Previous'][aria-disabled='true']"

