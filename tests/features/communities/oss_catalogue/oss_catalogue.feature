@api @group-clone
Feature:
  In order to present the details about EU OSS catalogue
  I want to view a dedicated collection and landing page

  Scenario: User is redirected from the standard collection URL.
    Given I visit "/collection/eu-oss-catalogue"
    Then I should be on "/eu-oss-catalogue"

  Scenario: User cannot access OSS Catalogue's Page Manager page
    # We assume page's path is '/eu-oss-catalogue-dummy'
    When I go to "/eu-oss-catalogue-dummy"
    Then the response status code should be 404

  Scenario: User sees the right content
    Given oss_solution content:
      | title              | oss_development_status | oss_source        | oss_categories                                                  | oss_short_description                                                                                                                         | status    | oss_release_date |
      | C4S ERP            | stable                 | developers_italia | content-management                                              | A lightweight SAML2 MDQ server that implements draft-young-md-query implementation.                                                           | published | 2022-04-13T13:00 |
      | Vito Schiraldi     | stable                 | developers_italia | data-analytics,data-collection                                  | Docker image to convert QuakeML format to NonLinLoc PHS format.                                                                               | published | 2000-07-13T13:00 |
      | Spunta             | beta                   | developers_italia | digital-asset-management                                        |                                                                                                                                               | published | 2023-08-13T13:00 |
      | Semantic Turkey    | development            | developers_italia | application-development,it-development,digital-asset-management | Software Registry makes software development phase simplier by showing extended information about projects and allowing source code searches. | published | 2000-04-13T13:00 |
      | Cristian Gagliardi | obsolete               | developers_italia | data-collection                                                 | Immuni is a mobile app that helps us to fight the COVID-19 pandemic by notifying users at risk of carrying the virus as early as possible     | published | 2020-09-13T13:00 |

    When I visit "/eu-oss-catalogue"

    # Check menus.
    Then I should not see the link "Overview" in the "Navigation menu"
    And I should not see the link "Members" in the "Navigation menu"
    And I should not see the link "About" in the "Navigation menu"
    But I should see the link "Welcome" in the "Navigation menu"
    And I should see the link "Search" in the "Navigation menu"
    And I should not see the "Entity actions" region

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

    # Check inaccessible routes.
    When I visit "/eu-oss-catalogue/about"
    Then I should get an access denied error
    When I visit "/eu-oss-catalogue/members"
    Then I should get an access denied error
