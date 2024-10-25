@group-clone @api
Feature: Minimal install clone testing.

  Scenario: Visit Portal collection.

    Given I am an anonymous user
    When I visit "/search?keys=joinup&f[0]=type:collection"
    Then I should see the "The Portal" tile
    And I should see "The European Commission provides this common place"

  Scenario: Assert that the cache backend is set to memcache.
    Given the default cache backend is set to "cache.backend.memcache"

  @javascript
  Scenario: Navbar highlight hub when user is on hub's landing page
    Given I am an anonymous user
    When I visit "/interoperable_solutions"
    Then I should see the link "Solutions"
    And "Solutions" should be the active item in the "Navigation bar" region
    Then I visit "/collection/portal"
    And no menu items should be active in the "Navigation bar" region
