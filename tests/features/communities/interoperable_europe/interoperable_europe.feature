@api @group-clone
Feature:
  In order to present the details about Interoperable Europe
  As a content editor
  I want to manage a dedicated collection and landing page

  Scenario: Anonymous visiting the landing page.
    Given I am not logged in
    And I am on the homepage
    # A dedicated link in the header should lead to the landing page.
    When I click "Interoperable Europe" in the Header region
    # TODO: The page title is temporary.
    Then I should see "IOP landing page"
    # Check that we are on the expected URL for the landing page.
    And I should be on "/collection/interoperable-europe"

    Given I am on the homepage
    When I click "Discover more"
    # TODO: The page title is temporary.
    Then I should see "IOP landing page"
    And the page should be rendered using the "Interoperable Europe" theme

    # Test other pages.
    When I visit "/collection/interoperable-europe/initiatives"
    Then the page should be rendered using the "Interoperable Europe" theme

    # Event and News item canonical pages are still rendered with the legacy
    # theme until ISAICP-7253 and ISAICP-7255.
    # See https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-7253.
    # See https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-7255.
    When I visit "/collection/interoperable-europe/event/save-date-semic-coming-back-8-december-2021"
    Then the page should be rendered using the "Joinup Theme" theme
    When I visit "/collection/interoperable-europe/news/vocbench-semantic-technologies-data-management"
    Then the page should be rendered using the "Joinup Theme" theme

  Scenario: Authenticated user visiting the landing page.
    Given I am logged in as a user with the authenticated role
    When I check the box "I agree to the Legal notice document"
    And I press "Submit"
    Then I should see the link "Interoperable Europe"

    # A dedicated link in the header should lead to the landing page.
    When I click "Interoperable Europe" in the Header region
    # TODO: The page title is temporary.
    Then I should see "IOP landing page"
    # Check that we are on the expected URL for the landing page.
    And I should be on "/collection/interoperable-europe"
    And the page should be rendered using the "Interoperable Europe" theme

    # Check IOPE menu
    Then I should see the link "News" in the "IOPE Menu"
    And I should see the link "Events" in the "IOPE Menu"
    And I should see the link "Policy" in the "IOPE Menu"
