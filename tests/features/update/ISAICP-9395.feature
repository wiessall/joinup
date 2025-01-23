@api @group-clone
Feature: Sample test of the eGovERA import.

  @update:joinup_core_deploy_200200
  Scenario: Importing of eGovERA data is complete.
    Given I go to the "Adequacy Recognition" egovera term page
    Then I should see the heading "Adequacy Recognition"
    # A row from the extra properties table.
    And I should see the text "The decision describes the justification for why the particular solution was accepted. It has more emphasis on the why rather than the how."

  @update:joinup_core_deploy_200200
  Scenario: Test that eGovERA PURIs are working.
    When I visit the page of the entity having "http://data.europa.eu/dr8/egovera/DigitalInvoicingCapability" as RDF resource
    Then I should see the heading "Digital Invoicing"
    And I should see the text "http://data.europa.eu/dr8/egovera/DigitalInvoicingCapability"
