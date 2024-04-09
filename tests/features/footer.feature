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
