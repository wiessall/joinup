@api @group-clone
Feature: Track usage and file visibility work for resource file paragraphs.

  @update:joinup_paragraphs_deploy_200600
  Scenario Outline:
    Given I go to "<path>"
    Then the response status code should be <response_code>

    Examples:
      | path                                                                                                                | response_code |
      | /sites/default/files/document/2025-08/national-policy-brief_oots_v1.pdf                                             | 200           |
      | /sites/default/files/document/2011-12/IDABC.OSOR.casestudy.LiMux.pdf                                                | 200           |
      | /sites/default/files/document/2018-10/ESS_Version%201.0_A4_Statistical%20Production%20Reference%20Architecture.docx | 200           |
