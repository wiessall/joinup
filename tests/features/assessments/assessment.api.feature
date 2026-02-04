@api @group-e
Feature: Assessments API

  Scenario: Use API for assessment.
    Given solution content:
      | title           | uuid                                 |
      | Citizen's Forum | 993958d5-2892-413c-a239-3d7a66daa0a8 |
    Then a GET request to "/api/assessments/v1/asset" should return the following JSON:
    """
    rows:
      - id: 993958d5-2892-413c-a239-3d7a66daa0a8
        title: Citizen's Forum
        url: __base_url__/collection/portal-support/solution/citizens-forum
        uri: http://data.europa.eu/w21/993958d5-2892-413c-a239-3d7a66daa0a8
    pager:
      current_page: 0
      total_items: 1
      total_pages: 1
      items_per_page: 200
    """
    And the page should be cacheable
    But the page should not be cached
    When I go to "/api/assessments/v1/asset"
    Then the page should be cached

    Given public_organisation assessments:
      | name        | organisation_type          | uuid                                 | country    |
      | ACME        | european_union_institution | 5d8be6b1-8038-429e-b84e-6ca8c35fd9b0 |            |
      | Bad Company | local_public_sector_body   | e4893580-f468-4c11-921d-1fe68b8540ae | Luxembourg |
    And report assessments:
      | name     | provider    | uuid                                 | state     |
      | Report 1 | ACME        | 5d8be6b1-8038-429e-b84e-6ca8c35fd9b1 | published |
      | Report 2 | Bad Company | e4893580-f468-4c11-921d-1fe68b8540a2 | published |
      | Report 3 | Bad Company | e4893580-f468-4c11-921d-1fe68b8540a3 | draft     |
    Then a GET request to "/api/assessments/v1/organisation" should return the following JSON:
    """
    rows:
      - id: 5d8be6b1-8038-429e-b84e-6ca8c35fd9b0
        name: ACME
        organisation_type: european_union_institution
        country: null
        uri: http://data.europa.eu/s2k/5d8be6b1-8038-429e-b84e-6ca8c35fd9b0
      - id: e4893580-f468-4c11-921d-1fe68b8540ae
        name: Bad Company
        organisation_type: local_public_sector_body
        country: LU
        uri: http://data.europa.eu/s2k/e4893580-f468-4c11-921d-1fe68b8540ae
    pager:
      current_page: 0
      total_items: 2
      total_pages: 1
      items_per_page: 200
    """
    And the page should be cacheable
    But the page should not be cached
    When I go to "/api/assessments/v1/organisation"
    Then the page should be cached
    Then a GET request to "/api/assessments/v1/assessment" should return the following JSON:
    """
    rows:
    - id: 5d8be6b1-8038-429e-b84e-6ca8c35fd9b1
      name: Report 1
      country: null
      uri: http://data.europa.eu/s2k/5d8be6b1-8038-429e-b84e-6ca8c35fd9b1
    - id: e4893580-f468-4c11-921d-1fe68b8540a2
      name: Report 2
      country: LU
      uri: http://data.europa.eu/s2k/e4893580-f468-4c11-921d-1fe68b8540a2
    pager:
      current_page: 0
      total_items: 2
      total_pages: 1
      items_per_page: 200
    """
    Then a GET request to "/api/assessments/v1/assessment?country=LU" should return the following JSON:
    """
    rows:
    - id: e4893580-f468-4c11-921d-1fe68b8540a2
      name: Report 2
      country: LU
      uri: http://data.europa.eu/s2k/e4893580-f468-4c11-921d-1fe68b8540a2
    pager:
      current_page: 0
      total_items: 1
      total_pages: 1
      items_per_page: 200
    """
    Then a GET request to "/api/assessments/v1/assessment?country=EU" should return the following JSON:
    """
    rows:
    - id: 5d8be6b1-8038-429e-b84e-6ca8c35fd9b1
      name: Report 1
      country: null
      uri: http://data.europa.eu/s2k/5d8be6b1-8038-429e-b84e-6ca8c35fd9b1
    pager:
      current_page: 0
      total_items: 1
      total_pages: 1
      items_per_page: 200
    """
    Then I go to "/api/assessments/v1/assessment?country=FOOBAR"
    Then the response status code should be 404
