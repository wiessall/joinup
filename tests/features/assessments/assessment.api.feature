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
        uri: http://data.europa.eu/w21/993958d5-2892-413c-a239-3d7a66daa0a8
        title: Citizen's Forum
        url: __base_url__/collection/portal-support/solution/citizens-forum
    pager:
      current_page: 0
      items_per_page: 200
      total_items: 1
      total_pages: 1
    """
    And the page should be cacheable
    But the page should not be cached
    When I go to "/api/assessments/v1/asset"
    Then the page should be cached

    Given public_organisation assessments:
      | name        | organisation_type          | uuid                                 | country    |
      | ACME        | european_union_institution | 5d8be6b1-8038-429e-b84e-6ca8c35fd9b0 |            |
      | Bad Company | local_public_sector_body   | e4893580-f468-4c11-921d-1fe68b8540ae | Luxembourg |
    Given the following assessment statements:
      | id  | statement_type | interpretation | judgement             |
      | 900 | legal          | neutral        | Needs more assessment |
    And report assessment:
      | name     | provider | uuid                                 | state     | results_in |
      | Report 1 | ACME     | 5d8be6b1-8038-429e-b84e-6ca8c35fd9b1 | published | 900        |
    And report assessments:
      | name     | provider    | uuid                                 | state     |
      | Report 2 | Bad Company | e4893580-f468-4c11-921d-1fe68b8540a2 | published |
      | Report 3 | Bad Company | e4893580-f468-4c11-921d-1fe68b8540a3 | draft     |
    Then a GET request to "/api/assessments/v1/organisation" should return the following JSON:
    """
    rows:
      - id: 5d8be6b1-8038-429e-b84e-6ca8c35fd9b0
        uri: http://data.europa.eu/s2k/5d8be6b1-8038-429e-b84e-6ca8c35fd9b0
        name: ACME
        organisation_type: european_union_institution
        country: null
      - id: e4893580-f468-4c11-921d-1fe68b8540ae
        uri: http://data.europa.eu/s2k/e4893580-f468-4c11-921d-1fe68b8540ae
        name: Bad Company
        organisation_type: local_public_sector_body
        country: LU
    pager:
      current_page: 0
      items_per_page: 200
      total_items: 2
      total_pages: 1
    """
    And the page should be cacheable
    But the page should not be cached
    When I go to "/api/assessments/v1/organisation"
    Then the page should be cached
    Then a GET request to "/api/assessments/v1/assessment" should return the following JSON:
    """
    rows:
    - id: 5d8be6b1-8038-429e-b84e-6ca8c35fd9b1
      uri: http://data.europa.eu/s2k/5d8be6b1-8038-429e-b84e-6ca8c35fd9b1
      name: Report 1
      country: null
    - id: e4893580-f468-4c11-921d-1fe68b8540a2
      uri: http://data.europa.eu/s2k/e4893580-f468-4c11-921d-1fe68b8540a2
      name: Report 2
      country: LU
    pager:
      current_page: 0
      items_per_page: 200
      total_items: 2
      total_pages: 1
    """
    Then a GET request to "/api/assessments/v1/assessment?country=LU" should return the following JSON:
    """
    rows:
    - id: e4893580-f468-4c11-921d-1fe68b8540a2
      uri: http://data.europa.eu/s2k/e4893580-f468-4c11-921d-1fe68b8540a2
      name: Report 2
      country: LU
    pager:
      current_page: 0
      items_per_page: 200
      total_items: 1
      total_pages: 1
    """
    Then a GET request to "/api/assessments/v1/assessment?country=EU" should return the following JSON:
    """
    rows:
    - id: 5d8be6b1-8038-429e-b84e-6ca8c35fd9b1
      uri: http://data.europa.eu/s2k/5d8be6b1-8038-429e-b84e-6ca8c35fd9b1
      name: Report 1
      country: null
    pager:
      current_page: 0
      items_per_page: 200
      total_items: 1
      total_pages: 1
    """
    Then I go to "/api/assessments/v1/assessment?country=FOOBAR"
    Then the response status code should be 404

    Then a GET request to "/api/assessments/v1/assessment/5d8be6b1-8038-429e-b84e-6ca8c35fd9b1" should return the following JSON:
    """
    id: 5d8be6b1-8038-429e-b84e-6ca8c35fd9b1
    uri: http://data.europa.eu/s2k/5d8be6b1-8038-429e-b84e-6ca8c35fd9b1
    language: en
    name: Report 1
    provider:
      id: 5d8be6b1-8038-429e-b84e-6ca8c35fd9b0
      uri: http://data.europa.eu/s2k/5d8be6b1-8038-429e-b84e-6ca8c35fd9b0
      name: ACME
      organisation_type: european_union_institution
      country: null
    relevant_report: []
    binding_requirement: []
    results_in:
      legal:
        interpretation: neutral
        judgement: Needs more assessment
    remaining_barriers: null
    documents: []
    other_comment: null
    """
