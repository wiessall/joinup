@api @group-clone
Feature:
  In order to present the details about Interoperable Europe
  As a content editor
  I want to manage a dedicated collection and landing page

  Scenario: Anonymous visiting the landing page.
    Given I am on the homepage
    When I click "Discover more"
    # The title should not be visible when the page is set as landing page.
    Then I should not see "IOP landing page"
    And the page should be rendered using the "Interoperable Europe" theme
    And I should not see the link "Report abusive content"

    # Test other pages.
    When I visit "/interoperable-europe/initiatives"
    Then the page should be rendered using the "Interoperable Europe" theme
    And I should see the heading "Initiatives"
    And I should see the heading "Join our community!"
    And I should not see the link "Report abusive content"

    # Event.
    When I visit "/interoperable-europe/event/save-date-semic-coming-back-8-december-2021"
    Then the page should be rendered using the "Interoperable Europe" theme
    And I should see the text "Event"
    And I should not see the heading "Event"
    And I should see the link "more events"
    And I should see the link "Report abusive content"
    And I should see the text "Published on: 19/11/2021"
    And I should see the heading "Join our community!"
    When I click "more events"
    Then I should see the heading "Events"

    # News.
    When I visit "/interoperable-europe/news/vocbench-semantic-technologies-data-management"
    Then the page should be rendered using the "Interoperable Europe" theme
    And I should see the text "News"
    And I should not see the heading "News"
    And I should see the link "more news"
    And I should see the link "Report abusive content"
    And I should see the text "Published on: 19/11/2021"
    And I should see the heading "Join our community!"
    When I click "more news"
    Then I should see the heading "News"

  Scenario: Authenticated user visiting the landing page.
    Given I am logged in as a user with the authenticated role
    When I check the box "I agree to the Legal notice document"
    And I press "Submit"
    When I go to the "Interoperable Europe" collection
    Then I should not see "IOP landing page"
    # Check that we are on the expected URL for the landing page.
    And I should be on "/interoperable-europe"
    And the page should be rendered using the "Interoperable Europe" theme

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
  Scenario: User should see error message if he does not fill out all required fields.
    Given custom_page content:
      | title               | collection           | state     |
      | IOP validation page | Interoperable Europe | published |

    Then I am logged in as a moderator
    And I go to the edit form of the "IOP validation page" "custom page"
    And I fill in "Title" with ""
    And I press "Update"
    Then I should see the error message "Title field is required."

  @javascript
  Scenario: Report link and eTranslate button are not available in IOP custom pages.
    Given custom_page content:
      | title    | collection           | state     |
      | IOP page | Interoperable Europe | published |

    When I am logged in as a user with the authenticated role
    And I go to the "IOP page" custom page
    Then I should not see the link "Report abusive content"
    And I should not see the button "Translate"

  @javascript
  Scenario Outline: Report link and eTranslate button are not available in IOP.
    Given <type> content:
      | title      | collection           | state     |
      | IOP <type> | Interoperable Europe | published |

    When I am logged in as a user with the authenticated role
    And I go to the "IOP <type>" <type>
    Then I should not see the link "Report abusive content"
    And I should not see the button "Translate"

    Examples:
      | type       |
      | news       |
      | event      |
      | discussion |
      | document   |

  @javascript
  Scenario: Regression test that ensures that search bar works fine.
    Given I go to "/interoperable-europe"
    # The search bar icon has a hidden accessibility label "Toggle search box".
    And I press "Toggle search box"
    And I enter "About" in the search bar and press enter
