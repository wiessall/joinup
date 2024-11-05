@api @group-a
Feature: Validate an ADMS-AP file through the UI
  In order to be sure that my ADMS data is compliant
  As a user
  I need to be able to publish an RDF file

  @loggedErrors
  Scenario: Validate a file
    When I am an anonymous user
    And I go to the homepage
    Then I should not see the link "ADMS-AP Validator"

    When I go to "/adms-validator"
    Then I should see the heading "ADMS-AP Validator"

    # Submitting the form without adding any file should not cause any PHP
    # warnings.
    Given there are no preexisting log records on the site
    And I press "Upload"
    Then I should see the error message "Please upload a valid RDF file."
    And the site should not have any log records

    When I attach the file "invalid_adms.rdf" to "File"
    And I press "Upload"
    Then I should see the text "16 schema error(s) were found while validating."
    And I should see the text "The dcat:Dataset http://example.com/solution/1 does not have a dcat:contactPoint property." in the "dcat:contactPoint is a required property for dcat:Dataset." row

    # Check file non-compliant to ADMS v2.
    When I attach the file "valid_adms.rdf" to "File"
    And I press "Upload"
    Then I should see the text "No errors found during validation."

    # Fix this in https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-4880
    # # Check incorrect file.
    # When I attach the file "empty.rdf" to "File"
    # And I press "Upload"
    # Then I should see the following error messages:
    # | error messages                             |
    # | The provided file is not a valid RDF file. |
