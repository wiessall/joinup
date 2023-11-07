@group-clone @api
Feature: Test navbar migration.

  Scenario: Anonymous visiting the landing page.
    Given I am not logged in
    When I am on the homepage
    And I click "Interoperable Europe" in the "Navigation bar"
    Then I should be on "/interoperable-europe"

    When I am on the homepage
    And I click "Interoperability Solutions" in the "Navigation bar"
    Then I should be on "/collection/joinup/interoperability-solutions"
