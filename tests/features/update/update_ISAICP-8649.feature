@api @group-clone
Feature: Asset distributions have Other licence instead of a missing one.

  Scenario Outline: Regression test for distributions with missing licence
    And I go to "<url>"
    Then the response status code should be 200
    And I should see the text "Licence: Other" in the Content region
    And I should see the link "Other" in the Content region

    Examples:
      | url                                                                                                                                                                                                                              |
      # Non-existing licence http://data.europa.eu/w21/dbd78bd7-c584-48c0-8c4d-be72cfe9e259
      | /collection/eu-semantic-interoperability-catalogue/solution/statistical-data-and-metadata-exchange/distribution/sdmx-21-section-1-framework |
      | /collection/eu-semantic-interoperability-catalogue/solution/statistical-data-and-metadata-exchange/distribution/sdmx-21-section-2-information-model |
      | /collection/eu-semantic-interoperability-catalogue/solution/statistical-data-and-metadata-exchange/distribution/sdmx-21-section-3a-sdmxml |
      | /collection/eu-semantic-interoperability-catalogue/solution/statistical-data-and-metadata-exchange/distribution/sdmx-21-section-3b-sdmx-ml |
      | /collection/eu-semantic-interoperability-catalogue/solution/statistical-data-and-metadata-exchange/distribution/sdmx-21-section-4-sdmx-edi |
      | /collection/eu-semantic-interoperability-catalogue/solution/statistical-data-and-metadata-exchange/distribution/sdmx-21-section-5-registry-specification-logical-interfaces |
      | /collection/eu-semantic-interoperability-catalogue/solution/statistical-data-and-metadata-exchange/distribution/sdmx-21-section-6-technical-notes |
      | /collection/eu-semantic-interoperability-catalogue/solution/statistical-data-and-metadata-exchange/distribution/sdmx-21-section-7-web-services-guidelines |
      | /collection/eu-semantic-interoperability-catalogue/solution/statistical-data-and-metadata-exchange-0/distribution/section-01-framework |
      | /collection/eu-semantic-interoperability-catalogue/solution/statistical-data-and-metadata-exchange-0/distribution/section-02-information-model-uml-conceptual-design |
      | /collection/eu-semantic-interoperability-catalogue/solution/statistical-data-and-metadata-exchange-0/distribution/section-03a-sdmx-ml |
      | /collection/eu-semantic-interoperability-catalogue/solution/statistical-data-and-metadata-exchange-0/distribution/section-03b-sdmx-ml |
      | /collection/eu-semantic-interoperability-catalogue/solution/statistical-data-and-metadata-exchange-0/distribution/section-04-sdmx-edi |
      | /collection/eu-semantic-interoperability-catalogue/solution/statistical-data-and-metadata-exchange-0/distribution/section-05-registry-specification-logical-interfaces |
      | /collection/eu-semantic-interoperability-catalogue/solution/statistical-data-and-metadata-exchange-0/distribution/section-06-implementors-guide-sdmx-standards |
      | /collection/eu-semantic-interoperability-catalogue/solution/statistical-data-and-metadata-exchange-0/distribution/section-07-web-services-guidelines |
      | /collection/eu-semantic-interoperability-catalogue/solution/statistical-data-and-metadata-exchange-0/distribution/zip-file-all-sdmx-standards-version-20 |
      | /collection/eu-semantic-interoperability-catalogue/solution/statistical-data-and-metadata-exchange-1/distribution/section-01-framework |
      | /collection/eu-semantic-interoperability-catalogue/solution/statistical-data-and-metadata-exchange-1/distribution/section-02-information-model-uml-conceptual-design |
      | /collection/eu-semantic-interoperability-catalogue/solution/statistical-data-and-metadata-exchange-1/distribution/section-03a-sdmx-ml |
      | /collection/eu-semantic-interoperability-catalogue/solution/statistical-data-and-metadata-exchange-1/distribution/section-03b-xml-schemas-samples |
      | /collection/eu-semantic-interoperability-catalogue/solution/statistical-data-and-metadata-exchange-1/distribution/section-04-sdmx-edi |
      | /collection/eu-semantic-interoperability-catalogue/solution/statistical-data-and-metadata-exchange-1/distribution/section-05-implementors-guide-sdmx-format-standards |
      | /collection/eu-semantic-interoperability-catalogue/solution/statistical-data-and-metadata-exchange-1/distribution/section-06-web-services-guidelines |
      | /collection/eu-semantic-interoperability-catalogue/solution/statistical-data-and-metadata-exchange-1/distribution/zip-file-pdf-documents-all-six-sections-sdmx-standards-version-10 |
      | /collection/eu-semantic-interoperability-catalogue/solution/aeronautical-information-exchange-model/distribution/aixm-uml-model |
      | /collection/eu-semantic-interoperability-catalogue/solution/aeronautical-information-exchange-model/distribution/aixm-xml-schema |
      | /collection/eu-semantic-interoperability-catalogue/solution/shortsea-xml/distribution/shortsea-xml-shedule-xml-schema |
      | /collection/eu-semantic-interoperability-catalogue/solution/shortsea-xml/distribution/shortsea-xml-booking-xml-schema |
      | /collection/eu-semantic-interoperability-catalogue/solution/shortsea-xml/distribution/shortsea-xml-manifest-xml-schema |
      | /collection/eu-semantic-interoperability-catalogue/solution/shortsea-xml/distribution/shortsea-xml-status-xml-schema |
      | /collection/eu-semantic-interoperability-catalogue/solution/specification-interoperable-access-edelivery-and-esafe-systems/distribution/specifications-interoperable-access-edelivery-and-esafe-systems-functional-specification |
      | /collection/eu-semantic-interoperability-catalogue/solution/weather-information-exchange-models/distribution/wxxm-eml-models |
      | /collection/eu-semantic-interoperability-catalogue/solution/weather-information-exchange-models/distribution/wxxm-xml-schemas |
      # Non-existing licence http://data.europa.eu/w21/db09e2bf-a3bf-458f-bd4c-3460646dd44b
      | /collection/spain-center-technology-transfer/solution/openjrubik-system-dissemination-statistical-data/distribution/openjrubik-system-dissemination-statistical-data |
      | /collection/spain-center-technology-transfer/solution/european-legislation-identifier/distribution/european-legislation-identifier                                   |

  Scenario: Distributions without licence are not modified
    And I go to "/collection/egovernment/solution/standard-data-e-forms/distribution/standarddaten-fur-e-formulare"
    Then the response status code should be 200
    And I should not see the text "Licence: Other" in the Content region
    And I should not see the link "Other" in the Content region

  Scenario: Other distributions are not touched
    And I go to "/collection/doris/solution/doris/distribution/doris-10"
    Then the response status code should be 200
    And I should see the text "Licence: European Union Public Licence, Version 1.2 or later (EUPL)" in the Content region
    And I should see the link "European Union Public Licence, Version 1.2 or later (EUPL)" in the Content region
