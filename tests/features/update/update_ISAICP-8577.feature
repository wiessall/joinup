@api @group-clone
Feature: Ensure contacts were migrated and exist.

  Scenario: Smoke test for the contact information.
    Given I go to "/collection/joinup"
    # Normally, we would check for a link but since the dump is sanitized, the
    # email confirmation is not present in a rebuilt site.
    Then I should see the text "Joinup Support Team"
    And I should see the text "Owner"
    And I should see the text "European Commission"
    And I should see the text "Supra-national authority"

  Scenario: Legacy RDF paths accessible only by moderators
    Given I am logged in as a moderator

    # Contact information
    When I visit "/rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f46c8f61e_b4034_b4137_b9c01_bb0274cf3a50c"
    Then the response status code should be 200
    And the HTML title of the page should be "Mathias Maurer"

    # Owner
    When I visit the homepage of the "Dirección de Modernizacion de la Gestión y Gobierno Electrónico. Gobierno de Misiones" owner
    Then the response status code should be 200
    And the HTML title of the page should be "Dirección de Modernizacion de la Gestión y Gobierno Electrónico. Gobierno de Misiones"

    # Provenance activity
    When I visit "/rdf_entity/https_e_f_fjoinup_cec_ceuropa_ceu_frdf_aentity_fprovenance_aactivity_f0ddf7736_be1f9_b4623_b829d_bc5d118dde2e2"
    Then the response status code should be 200
    And the HTML title of the page should be "Provenance of http://administracionelectronica.gob.es/contacto/ctt"
