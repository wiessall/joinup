@api @group-clone
Feature: Error when try to visit an old taxonomy term url.

  Scenario: Regression test for ISAICP-8736

  smoke_test.feature
    When I visit "/taxonomy/term/http_e_f_fdata_ceuropa_ceu_fdr8_fAPIGatewayTechnologyService"
    Then I should see "API Gateway"
