@api @group-clone
Feature: Tests ISAICP-8742.

  Scenario: Pages with broken references are logged.
    Given there are no preexisting log records
    And I go to "/collection/eu-semantic-interoperability-catalogue/solution/shortsea-xml/distribution/shortsea-xml-booking-xml-schema"
    Then I should see the heading "Shortsea XML - Booking XML Schema"
    And there should be an record of severity "Error" in the log with description "Can't find a mapped entity with URI http://data.europa.eu/w21/dbd78bd7-c584-48c0-8c4d-be72cfe9e259"
