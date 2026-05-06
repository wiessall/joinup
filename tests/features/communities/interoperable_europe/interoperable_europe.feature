@api @group-clone
Feature:
  In order to present the details about Interoperable Europe
  As a content editor
  I want to manage a dedicated collection and landing page

  Scenario: Moderator and facilitator check the three dots permissions.
    Given I am logged in as a moderator
    Then I go to the "Interoperable Europe" collection
    And I should see the link "Settings" in the "Entity actions" region

    When I am logged in as a facilitator of the "Interoperable Europe" collection
    When I check the box "I agree to the Legal notice document"
    And I press "Submit"
    When I go to the "Interoperable Europe" collection
    And I should see the link "Settings" in the "Entity actions" region

  Scenario: Anonymous visiting the landing page.
    When I visit "interoperable-europe/news/joinup-becoming-interoperable-europe-portal"
    And I should not see the "Entity actions" region
    And the page should be rendered using the "Interoperable Europe" theme
    And I should not see the link "Report abusive content"

    # Test other pages.
    When I visit "/interoperable-europe/initiatives"
    Then the page should be rendered using the "Interoperable Europe" theme
    And I see h1 span element with title "Initiatives"
    And I should see the heading "Join our community!"

    # Event.
    When I visit "/interoperable-europe/event/save-date-semic-coming-back-8-december-2021"
    Then the page should be rendered using the "Interoperable Europe" theme
    And I should see the text "Event"
    And I should see the link "more events"
    And I should see the text "Published on: 19/11/2021"
    And I should see the heading "Join our community!"
    When I click "more events"
    And I should see the text "Events"

    # News.
    When I visit "/interoperable-europe/news/vocbench-semantic-technologies-data-management"
    Then the page should be rendered using the "Interoperable Europe" theme
    And I should see the text "News"
    And I should see the link "more news"
    And I should see the text "Published on: 19/11/2021"
    And I should see the heading "Join our community!"
    When I click "more news"
    And I should see the text "News"

  Scenario: Authenticated user visiting the landing page.
    Given I am logged in as a user with the authenticated role
    When I check the box "I agree to the Legal notice document"
    And I press "Submit"
    When I go to the "Interoperable Europe" collection
    Then I should see "IOP landing page"
    # Check that we are on the expected URL for the landing page.
    And I should be on "/interoperable-europe"
    And I should not see the "Entity actions" region
    And the page should be rendered using the "Interoperable Europe" theme

    # Check IOPE footer
    Then I should see the heading "Join our community!"
    And I should see the following links:
      | Follow us on LinkedIn                            |
      | Follow us on X                                   |
      | Follow us on Youtube                             |
      | Subscribe to the interoperable europe newsletter |

    When I click "News"
    Then I should see the heading "Join our community!"
    And I should see the following links:
      | Follow us on LinkedIn                            |
      | Follow us on X                                   |
      | Follow us on Youtube                             |
      | Subscribe to the interoperable europe newsletter |

  Scenario: User should see error message if he does not fill out all required fields.
    Given the following "custom_page" content:
      | title               | collection           |
      | IOP validation page | Interoperable Europe |

    Then I am logged in as a moderator
    And I go to the edit form of the "IOP validation page" "custom page"
    And I fill in "Title" with ""
    And I press "Update"
    Then I should see the inline form error message "Title field is required."

  Scenario Outline: Visibility of Report link.
    Given the following "<type>" content:
      | title      | collection           |
      | IOP <type> | Interoperable Europe |

    When I am an anonymous user
    And I go to the "IOP <type>" <type>
    Then I should not see the link "Report abusive content"

    Examples:
      | type        |
      | news        |
      | event       |
      | discussion  |
      | document    |
      | custom_page |

  @javascript
  Scenario: Regression test that ensures that search bar works fine.
    Given I go to "/interoperable-europe"
    # The search bar icon has a hidden accessibility label "Toggle search box".
    And I press "Toggle search box"
    And I enter "About" in the search bar and press enter

  Scenario: Member management links appear in the members page.
    When I am logged in as a facilitator of the "Interoperable Europe" collection
    And I go to "/interoperable-europe/members"
    When I check the box "I agree to the Legal notice document"
    And I press "Submit"

    Then I should see the following links:
      | Invite members   |
      | Add announcement |
    # Add members is not available for facilitators, only moderators.
    But I should not see the link "Add members"
