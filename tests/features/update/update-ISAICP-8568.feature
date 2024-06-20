@api @group-clone
Feature: Test distributions deployment.

  Scenario Outline: Sample check for the path alias of distributions.
    And I go to "<url>"
    Then the response status code should be 200
    And I should see the heading "<heading>"

    Examples:
      | url                                                                                                                                                 | heading                                  |
      | /collection/eu-semantic-interoperability-catalogue/solution/statistical-data-and-metadata-exchange/distribution/sdmx-21-section-1-framework         | SDMX 2.1 - Section 1 – Framework         |
      | /collection/eu-semantic-interoperability-catalogue/solution/statistical-data-and-metadata-exchange/distribution/sdmx-21-section-2-information-model | SDMX 2.1 - Section 2 – Information Model |
