@api @interoperable-europe
Feature:
  In order to present the details about Interoperable Europe
  As a content editor
  I want to manage a dedicated collection and landing page

  Scenario Outline: Page customizations for the Interoperable Europe collection
    Given I am <user>
    And I go to "/collection/interoperable-europe/interoperable-europe"

    # The link to join the collection has a customized label.
    And I should see the <element> "Subscribe for fresh content"

    Examples:
      | user                                            | element |
      | not logged in                                   | link    |
      | logged in as a user with the authenticated role | button  |
