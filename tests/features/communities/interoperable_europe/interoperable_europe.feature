@api @group-clone
Feature:
  In order to present the details about Interoperable Europe
  As a content editor
  I want to manage a dedicated collection and landing page

  Scenario: Anonymous visiting the landing page.
    Given I am not logged in
    And I am on the homepage
    # A dedicated link in the header should lead to the landing page.
    When I click "Interoperable Europe" in the Header region
    # TODO: The page title is temporary.
    Then I should see "IOP landing page"
    # Check that we are on the expected URL for the landing page.
    And I should be on "/collection/interoperable-europe"

    Given I am on the homepage
    When I click "Discover more"
    # TODO: The page title is temporary.
    Then I should see "IOP landing page"

  Scenario: Authenticated user visiting the landing page.
    Given I am logged in as a user with the authenticated role
    When I check the box "I agree to the Legal notice document"
    And I press "Submit"
    Then I should see the link "Interoperable Europe"

    # A dedicated link in the header should lead to the landing page.
    When I click "Interoperable Europe" in the Header region
    # TODO: The page title is temporary.
    Then I should see "IOP landing page"
    # Check that we are on the expected URL for the landing page.
    And I should be on "/collection/interoperable-europe"
