@group-clone @api
Feature: Old about paths are redirected to the host entity canonical page.

  Scenario Outline: About pages with raw base path are redirected
    Given I am <user>
    When I visit "/rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_fe4618445_b6199_b42fc_b94df_b0b0395fd7878/about"
    Then the response status code should be 200

    Examples:
     | user                                 |
     | an anonymous user                    |
     | logged in as an "authenticated user" |
     | logged in as a moderator             |

  Scenario Outline: About pages with obsolete base path alias are redirected
    Given I am <user>
    When I visit "/collection/semantic-interoperability-community-semic/solution/vocbench/about"
    Then the response status code should be 200

    Examples:
      | user                                 |
      | an anonymous user                    |
      | logged in as an "authenticated user" |
      | logged in as a moderator             |
