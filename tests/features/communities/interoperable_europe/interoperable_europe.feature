@api @interoperable-europe
Feature:
  In order to present the details about Interoperable Europe
  As a content editor
  I want to manage a dedicated collection and landing page

  @a
  Scenario: Page customizations for the Interoperable Europe collection
    Given I am on the homepage
    # A dedicated link in the header should lead to the landing page.
    When I click "Interoperable Europe" in the Header region
    Then I should see the heading "Interoperable Europe"
    # Check that we are on the expected URL for the landing page.
    And I should be on "/collection/interoperable-europe/interoperable-europe"

    # The link to join the collection has a customized label.
    And I should see the link "Subscribe for fresh content"
