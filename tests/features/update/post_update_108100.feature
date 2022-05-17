@api @group-clone
Feature: Test update joinup_core_post_update_108100.

  Scenario: Ensure that solution types have been imported.
    Given I visit "/taxonomy/term/http_e_f_fdata_ceuropa_ceu_feif_fSolutionType_f0670b7fa_bf56d_b4c16_b918d_b82ca359c4331"
    Then I should see the heading "Common services"
