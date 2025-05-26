@api @group-clone
Feature: Tests for ISAICP-9800

  @update:joinup_assessment_deploy_200500
  Scenario: File access for published assessment reports was updated
    Given I am an anonymous user

    When I go to "/collection/assessments/assessment-reports/2042"
    And I click "formular_OHA_RozvojPortaluStudujNaVS_2verze_DIAP0C0NK6U1.pdf"
    Then the response status code should be 200

    Given I move backward one page
    When I click "Priloha_c1_oha_iea_priloha_zadosti_o_stanovisko_cs_final_DIAP0C0NK6VW.pdf"
    Then the response status code should be 200

    Given I move backward one page
    When I click "Formular_OHA_RozvojPortaluStudujNaVS_final_DIAP0C0N65RP.pdf"
    Then the response status code should be 200

    Given I move backward one page
    When I click "Priloha_c1_oha_iea_priloha_zadosti_o_stanovisko_cs_final_DIAP0C0NK6VW_0.pdf"
    Then the response status code should be 200
