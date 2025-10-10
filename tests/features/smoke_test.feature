@group-clone @api
Feature: Minimal install clone testing.

  Scenario: Visit Interoperable Europe Portal collection.

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

  @javascript
  Scenario: Assert publiccode.yml page.
    Given I am an anonymous user
    When I visit "/publiccode-editor"
    And I should see "publiccode.yml Editor"

  @javascript
  Scenario: Regression test for "Reporting Statistics per group".
    Given I am logged in as a user with the moderator role
    When I am on "/admin/reporting/statistics-per-group"
    Then I should see the heading "Statistics per group"
    And I press "Generate data"
    Then I should see the heading "Exporting statistics per group"
    And I wait for the batch process to finish

  Scenario: Access the file fields report page
    # Regression test for ticket ISAICP-10240.
    Given I am logged in as a user with the developer role
    When I am on "/admin/reports/fields/file"
    Then the response status code should be 200
