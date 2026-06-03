@api @group-clone
Feature:
  Testing ISAICP-11138 functionalities.

  @update:joinup_core_deploy_201300
  Scenario: Assert that the client's requested SPDX terms now show.
    Given I am logged in as a "licence_manager"
    And I check the box "I agree to the Legal notice document"
    And I press "Submit"
    And I go to "/node/add/licence"
    And I select "European Space Agency Public License – v2.4 – Permissive (Type 3)" from "Corresponding SPDX licence"
    And I select "European Space Agency Public License – v2.4 – Weak Copyleft (Type 2)" from "Corresponding SPDX licence"
    And I select "European Space Agency Public License (ESA-PL) - V2.4 - Strong Copyleft (Type 1)" from "Corresponding SPDX licence"
