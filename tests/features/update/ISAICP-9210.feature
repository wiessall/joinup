@api @group-clone
Feature: Test share functionality for solution.

  @update:joinup_core_deploy_200100 @javascript
  Scenario Outline: Check share functionality for solution
    Given I visit "<url>"
    And I click "Share"
    Then a modal should open
    And I should see the following lines of text:
      | Share on               |
      | Facebook               |
      | Twitter                |
      | Linkedin               |

    Examples:
      | url                                                                                                                           |
      | /collection/spain-center-technology-transfer/solution/adaptation-accessing-electronic-dossier                                 |
      | /collection/spain-center-technology-transfer/solution/local-administration-ocw-territorial-information-systems-local-entities |
      | /collection/spain-center-technology-transfer/solution/electronic-headquarters-type-government-canary-islands                  |
      | /collection/spain-center-technology-transfer/solution/external-signs-documents-jcyl                                           |
      | /collection/spain-center-technology-transfer/solution/integrated-system-management-population-and-territory                   |
