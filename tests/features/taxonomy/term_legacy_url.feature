@group-clone @api
Feature: Tests the term legacy URL redirection.

  Scenario: Legacy URLs are redirecting to new term canonical pages

    Given I go to "/taxonomy/term/http_e_f_fpublications_ceuropa_ceu_fresource_fauthority_fcountry_fAND"
    Then the current relative url should match "#^/taxonomy/term/\d+$#"
    When I go to "/taxonomy/term/http_e_f_fpublications_ceuropa_ceu_fresource_fauthority_fcountry_fAND?_format=n3"
    Then the current relative url should match "#^/taxonomy/term/\d+\?_format=n3$#"
