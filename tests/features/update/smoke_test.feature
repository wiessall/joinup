@group-clone @api
Feature: Minimal install clone testing.

  Scenario: Visit Joinup collection.

    Given I am an anonymous user
    When I visit "/collection/joinup"
    Then I should see the link "Federation of solutions"

    When I visit "/search?keys=joinup&f[0]=type:collection"
    Then I should see the "Joinup" tile
    And I should see "The European Commission provides this common place"
  Scenario: Assert that the cache backend is set to memcache.
    Given the default cache backend is set to "cache.backend.memcache"
