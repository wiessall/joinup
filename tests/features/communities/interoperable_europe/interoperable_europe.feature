@api @interoperable-europe
Feature:
  In order to present the details about Interoperable Europe
  As a content editor
  I want to manage a dedicated collection and landing page

  Scenario Outline: Page customizations for the Interoperable Europe collection
    Given I am <user>
    And I am on the homepage
    # A dedicated link in the navigation bar should lead to the landing page.
    Then I should see the link "Interoperable Europe" in the "Navigation bar"
    When I click "Interoperable Europe"
    Then I should see the heading "Interoperable Europe"
    # Check that we are on the expected URL for the landing page.
    And I should be on "/collection/interoperable-europe/interoperable-europe"

    # The link to join the collection has a customized label.
    And I should see the <element> "Subscribe for fresh content"

    Examples:
      | user                                            | element |
      | not logged in                                   | link    |
      | logged in as a user with the authenticated role | button  |

  Scenario: Discover more link on the homepage leads to the Interoperable Europe landing page
    Given I am on the homepage
    When I click "Discover more"
    Then I should see the heading "Interoperable Europe"
