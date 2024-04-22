@api @group-clone
Feature: Refresh legacy content to rectify Topic term name.

  Scenario: Smoke test search page

    Given I am an anonymous user
    When I visit "/search?f%5B0%5D=topic%3Ahttp%3A//joinup.eu/ontology/topic%23content-and-knowledge-management&f%5B1%5D=topic%3Ahttp%3A//joinup.eu/ontology/topic%23demography-and-population&f%5B2%5D=topic%3Ahttp%3A//joinup.eu/ontology/topic%23egovernment&f%5B3%5D=topic%3Ahttp%3A//joinup.eu/ontology/topic%23ehealth&f%5B4%5D=topic%3Ahttp%3A//joinup.eu/ontology/topic%23einclusion&f%5B5%5D=topic%3Ahttp%3A//joinup.eu/ontology/topic%23eparticipation-and-evoting&f%5B6%5D=topic%3Ahttp%3A//joinup.eu/ontology/topic%23open-source-software&f%5B7%5D=topic%3Ahttp%3A//joinup.eu/ontology/topic%23reusable-software-components&f%5B8%5D=topic%3Ahttp%3A//joinup.eu/ontology/topic%23science-and-technology&f%5B9%5D=topic%3Ahttp%3A//joinup.eu/ontology/topic/category%23digital-government&f%5B10%5D=topic%3Ahttp%3A//joinup.eu/ontology/topic/category%23education&f%5B11%5D=topic%3Ahttp%3A//joinup.eu/ontology/topic/category%23health&f%5B12%5D=topic%3Ahttp%3A//joinup.eu/ontology/topic/category%23ict"
    Then the page should not contain any facet summary
    And I should see "No content found for your search."
