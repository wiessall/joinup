@api @group-i
Feature:
  - As a moderator, in order to maintain the navbar, I am able to administer the 'navbar' menu.
  - As a user, I should see the new navbar link.

  Scenario: Moderator can add new item to navbar.
    Given I am logged in as a moderator
    # Add enabled link.
    When I go to "/admin/structure/menu/manage/navbar"
    And I click "Add link"
    And I fill in "Menu link title" with "Homepage-1"
    And I fill in "Link" with "<front>"
    And I press "Save"
    # Add disabled link.
    And I click "Add link"
    And I fill in "Menu link title" with "Homepage-2"
    And I fill in "Link" with "<front>"
    And I uncheck the box "Enabled"
    And I press "Save"
    # Add an external link.
    And I click "Add link"
    And I fill in "Menu link title" with "Homepage-3"
    And I fill in "Link" with "http://example.com"
    And I press "Save"

    Given I am an anonymous user
    When I am on the homepage
    Then I should see the link "Homepage-1" in the "Navigation bar" region
    And I should see the link "Homepage-3" in the "Navigation bar" region
    But I should not see the link "Homepage-2" in the "Navigation bar" region
