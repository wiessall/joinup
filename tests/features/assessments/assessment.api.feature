@api @group-e
Feature: Assessments API

  Scenario: Use API for assessment.
    Given solution content:
      | title           | uuid                                 |
      | Citizen's Forum | 993958d5-2892-413c-a239-3d7a66daa0a8 |
    Then a GET request to "/api/assessments/v1/asset" should return the following JSON:
    """
    - id: 993958d5-2892-413c-a239-3d7a66daa0a8
      title: Citizen's Forum
      url: __base_url__/collection/portal-support/solution/citizens-forum
    """

    Given public_organisation assessments:
      | name        | organisation_type          | uuid                                 | country    |
      | ACME        | european_union_institution | 5d8be6b1-8038-429e-b84e-6ca8c35fd9b0 |            |
      | Bad Company | local_public_sector_body   | e4893580-f468-4c11-921d-1fe68b8540ae | Luxembourg |
    Then a GET request to "/api/assessments/v1/public-organisation" should return the following JSON:
    """
    - id: 5d8be6b1-8038-429e-b84e-6ca8c35fd9b0
      name: ACME
      organisation_type: european_union_institution
      country: null
    - id: e4893580-f468-4c11-921d-1fe68b8540ae
      name: Bad Company
      organisation_type: local_public_sector_body
      country: LU
    """
