@api @group-h
Feature:
  - As a moderator, in order to maintain the navbar, I am able to administer the 'navbar' menu.
  - As a user, I should see the new navbar link.

  Scenario: Moderator can add new item to navbar.
    Given I am logged in as a moderator
    # Add enabled link.
    When I go to "/admin/structure/menu/manage/navbar"
    And I click "Add link"
    And I fill in "Menu link title" with "Navbar test 1"
    And I fill in "Link" with "<front>"
    And I press "Save"
    # Add disabled link.
    And I click "Add link"
    And I fill in "Menu link title" with "Navbar test 2 disabled"
    And I fill in "Link" with "<front>"
    And I uncheck the box "Enabled"
    And I press "Save"
    # Add an external link.
    And I click "Add link"
    And I fill in "Menu link title" with "Navbar test 3 external"
    And I fill in "Link" with "http://example.com"
    And I press "Save"
    # Add a disabled child.
    And I click "Add link"
    And I fill in "Menu link title" with "Navbar test 1 child"
    And I fill in "Link" with "http://example.com"
    And I select "-- Navbar test 1" from "Parent link"
    And I uncheck the box "Enabled"
    And I press "Save"

    Given I am an anonymous user
    When I am on the homepage
    Then I should see the link "Navbar test 1" in the "Navigation bar" region
    And I should see the link "Navbar test 3 external" in the "Navigation bar" region
    But I should not see the link "Navbar test 2 disabled" in the "Navigation bar" region
    But I should not see the link "Navbar test 1 child" in the "Navigation bar" region

    # Delete menu links. Remove this in ISAICP-9486.
    # Note that if any failure happens above, these links aren't removed; that's
    # why we use descriptive menu link titles.
    And I delete the "Navbar test 1 child" "menu link"
    And I delete the "Navbar test 1" "menu link"
    And I delete the "Navbar test 2 disabled" "menu link"
    And I delete the "Navbar test 3 external" "menu link"
