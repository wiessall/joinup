@api
Feature: Header
  In order to allow users to quickly get to important sections of the site
  As a UX designer
  I want to place links that are relevant to the user's status in the header

  @joinup
  Scenario Outline: Header shows basic info and directs anonymous users to log in or create an account
    Given I am not logged in
    And I visit the <page>
    Then I should see the Interoperable Europe logo in the navigation bar
    And I should see the hamburger icon in the main navigation

    # The links "Home" and "About us" are only shown in the mobile menu.
    And I should see the following items in the main navigation:
      | link        | desktop menu | mobile menu |
      | Home        | not shown    | link        |
      | Sign in     | link         | link        |
      | Get started | button       | not shown   |
      | About us    | not shown    | link        |

    # Check that the links lead to the expected pages.
    When I click "Home" in the "Mobile menu"
    Then I should be on "/"

    Given I visit the <page>
    When I click "Sign in" in the "Navigation bar"
    Then I should see the heading "Sign in to continue"

    Given I visit the <page>
    When I click "Sign in" in the "Mobile menu"
    Then I should see the heading "Sign in to continue"

    Examples:
      | page         |
      # A page with the new Ventuno theme.
      | search page |
      # A page with the old Joinup Theme theme.
      | collection overview |

  @joinup @javascript
  Scenario Outline: The 'Get started' button opens a popup with information about EU Login
    Given I am not logged in
    And I visit the <page>

    # The popup is not shown initially.
    Then I should not see the text "Create an account"
    And I should not see the text "As a signed-in user you can create content, become a member of a community, receive notifications on your favourite solutions and topics, and access all other features available on the platform."

    # The popup appears when clicking on 'Get started'.
    When I press "Get started"
    And I wait until the page contains the text "Create an account"
    Then I should see the link "Create an account"
    And I should see the text "As a signed-in user you can create content, become a member of a community, receive notifications on your favourite solutions and topics, and access all other features available on the platform."

    # Even though the link claims to lead to an account creation page, we are in
    # fact obliged to authenticate through EU Login. If the user doesn't have an
    # EU Login account yet they can create one inside the EU Login portal. We
    # just have to check that the link leads to our mocked EU Login portal.
    When I click "Create an account"
    Then I should see the heading "Sign in to continue"

    Examples:
      | page         |
      # A page with the new Ventuno theme.
      | search page |
      # A page with the old Joinup Theme theme.
      | collection overview |
