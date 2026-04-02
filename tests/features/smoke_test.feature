@group-clone @api
Feature: Minimal install clone testing.

  Scenario: Assert that the cache backend is set to memcache.
    Given the default cache backend is set to "cache.backend.memcache"

  @javascript
  Scenario: Navbar highlight hub when user is on hub's landing page
    Given I am an anonymous user
    Then I visit "/collection/portal-support"
    And "Support Centre > Portal support" should be the active items in the "Navigation bar" region
