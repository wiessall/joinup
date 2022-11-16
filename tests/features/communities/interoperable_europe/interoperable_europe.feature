@api @group-clone
Feature:
  In order to present the details about Interoperable Europe
  As a content editor
  I want to manage a dedicated collection and landing page

  Scenario: Anonymous visiting the landing page.
    Given I am not logged in
    And I am on the homepage
    # A dedicated link in the header should lead to the landing page.
    When I click "Interoperable Europe" in the "Navigation bar"
    # The title should not be visible.
    Then I should not see "IOP landing page"
    # Check that we are on the expected URL for the landing page.
    And I should be on "/interoperable-europe"

    Given I am on the homepage
    When I click "Discover more"
    # The title should not be visible when the page is set as landing page.
    Then I should not see "IOP landing page"
    And the page should be rendered using the "Interoperable Europe" theme

    # Test other pages.
    When I visit "/interoperable-europe/initiatives"
    Then the page should be rendered using the "Interoperable Europe" theme

    # Event and News item canonical pages are still rendered with the legacy
    # theme until ISAICP-7253 and ISAICP-7255.
    # See https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-7253.
    # See https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-7255.
    When I visit "/interoperable-europe/event/save-date-semic-coming-back-8-december-2021"
    Then the page should be rendered using the "Joinup Theme" theme
    When I visit "/interoperable-europe/news/vocbench-semantic-technologies-data-management"
    Then the page should be rendered using the "Joinup Theme" theme

  Scenario: Authenticated user visiting the landing page.
    Given I am logged in as a user with the authenticated role
    When I check the box "I agree to the Legal notice document"
    And I press "Submit"
    Then I should see the link "Interoperable Europe"

    # A dedicated link in the header should lead to the landing page.
    When I click "Interoperable Europe" in the "Navigation bar"
    Then I should not see "IOP landing page"
    # Check that we are on the expected URL for the landing page.
    And I should be on "/interoperable-europe"
    And the page should be rendered using the "Interoperable Europe" theme

    # Check IOPE menu
    Then I should see the link "News" in the "IOPE Menu"
    And I should see the link "Events" in the "IOPE Menu"
    And I should see the link "Policy" in the "IOPE Menu"

    # Check IOPE footer
    Then I should see the heading "Join our community!"
    And I should see the following links:
      | Follow us on LinkedIn                            |
      | Follow us on Twitter                             |
      | Follow us on Youtube                             |
      | Subscribe to the interoperable europe newsletter |

    When I click "News"
    Then I should see the heading "Join our community!"
    And I should see the following links:
      | Follow us on LinkedIn                            |
      | Follow us on Twitter                             |
      | Follow us on Youtube                             |
      | Subscribe to the interoperable europe newsletter |

  @javascript
  Scenario: Report link and eTranslate button are not available in IOP custom pages.
    Given custom_page content:
      | title      | collection           | state     |
      | IOP page | Interoperable Europe | validated |

    When I am logged in as a user with the authenticated role
    And I go to the "IOP page" custom page
    Then I should not see the link "Report abusive content"
    And I should not see the button "Translate"

  @javascript
  Scenario Outline: Report link and eTranslate button are not available in IOP.
    Given <type> content:
      | title      | collection           | state     |
      | IOP <type> | Interoperable Europe | validated |

    When I am logged in as a user with the authenticated role
    And I go to the "IOP <type>" <type>
    Then I should not see the link "Report abusive content"
    And I should not see the button "Translate"

    Examples:
      | type        |
      | news        |
      | event       |
      | discussion  |
      | document    |
