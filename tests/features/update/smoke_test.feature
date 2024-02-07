@group-clone @api
Feature: Minimal install clone testing.

  Scenario: Visit Joinup collection.

    Given I am an anonymous user
    When I visit "/collection/joinup"
    Then I should see the link "Federation of solutions"

    When I visit "/search?keys=joinup&f[0]=type:collection"
    Then I should see the "Joinup" tile
    Then I should see "The European Commission created Joinup to provide a common venue that enables public administrations, businesses and citizens to share and reuse IT solutions and good practices, and facilitate communication and collaboration on IT projects across..."
  Scenario: Assert that the cache backend is set to memcache.
    Given the default cache backend is set to "cache.backend.memcache"
