@api @group_e
Feature: OSS catalogue search
  As a user of the site I can find solutions through the OSS catalogue search.

  Scenario: User can view the OSS catalogue search page.
    Then I visit the OSS search page
    When I should see the heading "Search in European Open-source Software Solutions Catalogue"
    And the following fields should be present "Sort by:,Start typing to search…"
    And I should see the text "No content found for your search."
    And I should see the button "Clear filters"

  Scenario: User is able to search.
    Given oss_solution content:
      | title                           | oss_development_status | oss_source        | oss_categories                                                  | oss_short_description                                                                                                                         | status    |
      | Django-MDQ                      | stable                 | developers_italia | content-management                                              | A lightweight SAML2 MDQ server that implements draft-young-md-query implementation.                                                           | published |
      | INGV - QuakeML to NonLinLoc PHS | stable                 | developers_italia | data-analytics,data-collection                                  | Docker image to convert QuakeML format to NonLinLoc PHS format.                                                                               | published |
      | NDC Semantic frontend           | beta                   | developers_italia | digital-asset-management                                        |                                                                                                                                               | published |
      | Software Registry               | development            | developers_italia | application-development,it-development,digital-asset-management | Software Registry makes software development phase simplier by showing extended information about projects and allowing source code searches. | published |
      | Immuni                          | obsolete               | developers_italia | data-collection                                                 | Immuni is a mobile app that helps us to fight the COVID-19 pandemic by notifying users at risk of carrying the virus as early as possible     | published |

    Then I visit the OSS search page
    # Check main area
    When I should see the text "SEARCH RESULTS (5)"
    # Check tiles.
    And I should see the "Django-MDQ" tile
    And I should see the "INGV - QuakeML to NonLinLoc PHS" tile
    And I should see the "NDC Semantic frontend" tile
    And I should see the "Software Registry" tile
    And I should see the "Immuni" tile
    # Check facets.
    And the "OSS category" checkbox facet should allow selecting the following values "application-development (1),content-management (1),data-analytics (1),data-collection (2),digital-asset-management (2),it-development (1)"
    And the "OSS development status" checkbox facet should allow selecting the following values "Beta (1),Development (1),Obsolete (1),Stable (2)"
    And the "OSS catalog" checkbox facet should allow selecting the following values "developers_italia"

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
