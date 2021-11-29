@api
Feature: Footer
  In order to allow users to access secondary pages
  As a UX designer
  I want to place links to these pages in the footer

  Scenario Outline: Footer content not managed through menus
    Given I am not logged in
    And I am on <page>
    Then I should see the Joinup logo in the footer
    And I should see the Joinup version in the footer

    Examples:
      | page         |
      # A page with the new Ventuno theme.
      | the homepage |
      # A page with the old Joinup Theme theme.
      | the search   |
