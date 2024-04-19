@api @group-clone
Feature: Access to the https://joinup.ec.europa.eu/about should return a 404.

  Scenario: Regression test about.
    Given I am an anonymous user
    When I visit "/about"
    Then the response status code should be 200
