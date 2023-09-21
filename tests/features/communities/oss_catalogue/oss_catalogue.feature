@api @group-clone
Feature:
  In order to present the details about EU OSS catalogue
  I want to view a dedicated collection and landing page

  Scenario: User is redirected from the standard collection URL.
    Given I visit "/collection/eu-oss-catalogue"
    Then I should be on "/eu-oss-catalogue"

  Scenario: User sees the right content
    Given oss_solution content:
      | title              | oss_development_status | oss_source        | oss_categories                                                  | oss_short_description                                                                                                                         | status    | oss_release_date |
      | C4S ERP            | stable                 | developers_italia | content-management                                              | A lightweight SAML2 MDQ server that implements draft-young-md-query implementation.                                                           | published | 2022-04-13 |
      | Vito Schiraldi     | stable                 | developers_italia | data-analytics,data-collection                                  | Docker image to convert QuakeML format to NonLinLoc PHS format.                                                                               | published | 2000-07-13 |
      | Spunta             | beta                   | developers_italia | digital-asset-management                                        |                                                                                                                                               | published | 2023-08-13 |
      | Semantic Turkey    | development            | developers_italia | application-development,it-development,digital-asset-management | Software Registry makes software development phase simplier by showing extended information about projects and allowing source code searches. | published | 2000-04-13 |
      | Cristian Gagliardi | obsolete               | developers_italia | data-collection                                                 | Immuni is a mobile app that helps us to fight the COVID-19 pandemic by notifying users at risk of carrying the virus as early as possible     | published | 2020-09-13 |

    When I visit "/eu-oss-catalogue"
    And I should see the following headings:
      | Discover our initiative                          |
      | Most recent solutions                            |
      | Do you want to publish your open souce solution? |
      | Let’s start with your software name              |
      | Frequently Asked Questions (FAQ)                 |
      | Follow our journey on the social media!          |
    And I should see the text "Just follow the guide!"
    And I should see the text "EU Open Source Solutions Catalogue"

    # Check carousel order.
    And I should see the following tiles in the correct order:
      | Spunta             |
      | C4S ERP            |
      | Cristian Gagliardi |
      | Vito Schiraldi     |
      | Semantic Turkey    |

    When I click "advanced search"
    And I should see the heading "Search in EU Open Source Solutions Catalogue"

  Scenario: User sees short description on OSS Solution page.
    Given oss_solution content:
      | title | oss_development_status | oss_source        | oss_categories | oss_short_description   | oss_git_description   | status | oss_release_date |
      | Foo   | development                 | developers_italia | data-analytics | Foo's short description | Foo's GIT description | published | 2014-11-19 |
    When I go to the content page of the type "oss_solution" with the title "Foo"
    Then I should see the text "Foo's short description"
    But I should not see the text "Foo's GIT description"

  Scenario: User sees GIT description on OSS Solution page if short description is missing.
    Given oss_solution content:
      | title | oss_development_status | oss_source        | oss_categories | oss_short_description   | oss_git_description   | status    | oss_release_date |
      | Foo   | stable                 | developers_italia | data-analytics |                         | Foo's GIT description | published | 2014-11-19       |
    When I go to the content page of the type "oss_solution" with the title "Foo"
    Then I should see the text "Foo's GIT description"

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
    Given an oss_platform term with the name "macOs"
    Given oss_solution content:
      | title                   | oss_available_languages | oss_development_status | oss_source        |  oss_categories                   | oss_long_description                                                                             | oss_short_description | oss_git_description | status    | oss_release_date | oss_documentation   | oss_features         | oss_git_url         | oss_landing_url     | oss_license      | oss_logo                         | oss_maintenance_contacts | oss_maintenance_contractors | oss_maintenance_type | oss_owner       | oss_platforms | oss_publiccode_yml_version | oss_scope                       | oss_screenshots                                                   | oss_software_type  | oss_software_version | oss_used_by                                        |
      | Super Awesome Software  | it,en,de                | stable                 | developers_italia | document-management,collaboration | The long description. A patient developer can make this long enough to be at least 5 lines long. | The short description | The GIT description | published | 2014-11-19       | https://example.com | Feature A, Feature B | https://example.com | https://example.com | GPL-2.0-or-later | https://placehold.co/200x200.png | FOSS Conference          | ACME Ltd.                   | community            | FOSS Conference | macOs         | 0.4                        | science-and-technology,security | https://placehold.co/600x400.png,https://placehold.co/400x400.png | standalone/desktop | 3.17.143             | Administration 1,Administration 2,Administration 3 |
    When I go to the content page of the type "oss_solution" with the title "Super Awesome Software"
    Then I should be on "/eu-oss-catalogue/solutions/super-awesome-software"
    And I should see the following headings:
      | Super Awesome Software |
      | Description            |
      | Features               |
      | Intended audience      |
      | Detailed information   |
      | Gallery                |
    And I should see the following lines of text:
      | The short description                                                                              |
      | Description                                                                                        |
      |   The long description. A patient developer can make this long enough to be at least 5 lines long. |
      | Main copyright owner                                                                               |
      |   FOSS Conference                                                                                  |
      | Features                                                                                           |
      |   Feature A                                                                                        |
      |   Feature B                                                                                        |
      | Supported languages                                                                                |
      |   it,                                                                                              |
      |   en,                                                                                              |
      |   de                                                                                               |
      | Development status                                                                                 |
      |   Stable                                                                                           |
      | License                                                                                            |
      |   GPL-2.0-or-later                                                                                 |
      | Platforms                                                                                          |
      |   macOs                                                                                            |
      | Maintenance type                                                                                   |
      |   Maintained by people donating their time to the project                                          |
      | Software type                                                                                      |
      |   Typically installed and run in a a desktop operating system environment                          |
      | Used by                                                                                            |
      |   3 administrations                                                                                |
      | Maintenance contractors                                                                            |
      |   ACME Ltd.                                                                                        |
      | Maintenance contacts                                                                               |
      |   FOSS Conference                                                                                  |
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
      # Gallery.
      | https://placehold.co/600x400.png |
      | https://placehold.co/400x400.png |
    # Clicking links of Categories, Providers or Audience must navigate to
    # search.
    When I click "document-management"
    Then I should be on "/eu-oss-catalogue/solutions"
    And I should see the following facet summary "document-management"
    When I go to the content page of the type "oss_solution" with the title "Super Awesome Software"
    And I click "Developers Italia"
    Then I should be on "/eu-oss-catalogue/solutions"
    And I should see the following facet summary "Developers Italia"
    When I go to the content page of the type "oss_solution" with the title "Super Awesome Software"
    And I click "science-and-technology"
    Then I should be on "/eu-oss-catalogue/solutions"
    And I should see the following facet summary "science-and-technology"

