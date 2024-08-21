@api @group-clone
Feature: Footer
  In order to allow users to access secondary pages
  As a UX designer
  I want to place links to these pages in the footer

  Scenario Outline: Footer content not managed through menus
    Given I am not logged in
    And I visit "<page>"
    Then I should see the Interoperable Europe logo in the footer
    And I should see the Joinup version in the footer

    Examples:
      | page                             |
      # Pages rendered with Ventuno theme.
      | /                                |
      | /search                          |
      # Page rendered with Interoperable Europe theme.
      | /interoperable-europe            |
      # Page rendered with the legacy Joinup Theme theme.
      | /collection/joinup               |

  Scenario: Regression test to show/hide menu item.
    # Regression test for ticket ISAICP-6301.
    Given I am logged in as a moderator
    When I click "Menus"
    And I click "List links" in the "Help and support" row
    Then I uncheck the "Enable How-to & video tutorials" row
    And I press "Save"
    Then I should see the success message "Menu Help and support has been updated."

    Then I uncheck the "Enable Contact Joinup Support" row
    And I press "Save"

    Given I am an anonymous user
    And I am on the homepage
    Then I should not see the link "How-to & video tutorials"
    Then I should not see the link "Contact Joinup Support"
    And I should see the link "Take a tour"

    Then I click "advanced search"
    And I should be on the advanced search page
    Then I should not see the link "How-to & video tutorials"
    Then I should not see the link "Contact Joinup Support"
    And I should not see the text "Take a tour"

    # Re-enable the How-to & video menu link.
    Given I am logged in as a moderator
    When I click "Menus"
    And I click "List links" in the "Help and support" row
    Then I check the "Enable How-to & video tutorials" row
    Then I check the "Enable Contact Joinup Support" row
    And I press "Save"

    And I am on the homepage
    Then I should see the link "How-to & video tutorials"
    Then I should see the link "Contact Joinup Support"
