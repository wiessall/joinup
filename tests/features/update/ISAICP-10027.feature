@api @group-clone
Feature: Track usage and file visibility work for resource file paragraphs.

  @update:joinup_paragraphs_deploy_200600
  Scenario Outline:
    Given I am logged in as a moderator
    Given I go to "<path>"
    And I click "<filename>"
    Then the response status code should be <response_code>

    Examples:
      | path                                                                                                            | filename                                                              | response_code |
      | /collection/interoperable-europe-academy/document/policy-brief-understanding-eu-once-only-technical-system-oots | national-policy-brief_oots_v1.pdf                                     | 200           |
      | /collection/open-source-observatory-osor/document/declaration-independence-limux-project-munich-pdf             | IDABC.OSOR.casestudy.LiMux.pdf                                        | 200           |
      | /collection/open-source-observatory-osor/document/declaration-independence-limux-project-munich                 | IDABC.OSOR.casestudy.LiMux.pdf                                        | 200           |
      | /collection/statistical-enterprise-architecture/document/ess-statistical-production-reference-architecture-0    | ESS_Version 1.0_A4_Statistical Production Reference Architecture.docx | 200           |
