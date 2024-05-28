@api @group-clone
Feature: Old URI ID of Statistics and information term is redirected.

  Scenario: Old URI ID of Statistics and information term is redirected.
    And I go to "/taxonomy/term/http_e_f_fjoinup_ceu_fontology_ftopic_hstatistics_band_binformatino"
    Then the response status code should be 200
    And the relative url should be "/search?f%5B0%5D=topic%3A11197"
