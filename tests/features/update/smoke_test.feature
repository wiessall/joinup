@group-clone @api
Feature: Minimal install clone testing.

  Scenario: Visit Joinup collection.

    Given I am an anonymous user
    When I visit "/collection/joinup"
    Then I should see the link "Federation of solutions"

    # Check that the Solr index has been restored.
    When I visit "/search?keys=joinup&f[0]=type:collection"
    Then I should see "The platform Joinup is the European Commission's one-stop shop for interoperable, open and free digital government ICT solutions"
