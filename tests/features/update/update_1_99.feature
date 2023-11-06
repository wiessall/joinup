@api @group-clone
Feature: Tests for the 1.99 release.

  Scenario: ISAICP-8225 - fix EIF toolbox solution menu.
    Given I am logged in as a moderator
    When I go to "/collection/nifo-national-interoperability-framework-observatory/solution/eif-toolbox/solutions-0"
    And I click the contextual link "Edit menu" in the "Header" region
    Then the response status code should be 200

  Scenario: ISAICP-8231 - Fix the missing EIRA namespace.
    Given I go to "/taxonomy/term/http_e_f_fdata_ceuropa_ceu_fdr8_fHostingFacility"
    And I should see the following table rows:
      | Namespace               | URI                                           |
      | owlturtle:              | http://www.semanticweb.org/owl/owlapi/turtle# |
      | Property                | Value                                         |
      | owlturtle:secure_access | [ Yes \| No ]                                 |
