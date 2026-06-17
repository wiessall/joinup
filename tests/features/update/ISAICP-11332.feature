@api @group-clone
Feature: Test ISAICP-11332 update path.

  @update:joinup_group_deploy_201400
  @update:joinup_group_deploy_201401
  @update:joinup_group_deploy_201402
  @update:joinup_group_deploy_201403
  @update:joinup_group_deploy_201404
  @update:joinup_group_deploy_201405
  @update:joinup_group_deploy_201406
  @update:joinup_group_deploy_201407
  Scenario: Test conversion of group menu link

    Given I go to "/collection/semic-support-centre"
    Then I should see the link "Welcome" in the "Navigation menu" region
    And I should see the link "Services" in the "Navigation menu" region
    And I should see the link "Events" in the "Navigation menu" region
    And I should see the link "Catalogue of Services" in the "Navigation menu" region
    And I should see the link "AI for Interoperability" in the "Navigation menu" region
    And I should see the link "Base Registries" in the "Navigation menu" region
    And I should see the link "Data Spaces" in the "Navigation menu" region
    And I should see the link "Knowledge Hub" in the "Navigation menu" region
    And I should see the link "SEMIC Conference" in the "Navigation menu" region
    And I should see the link "Overview" in the "Navigation menu" region
    But I should see the following lines of text in the correct order in "Navigation menu":
      | Welcome                 |
      | Services                |
      | Events                  |
      | Catalogue of Services   |
      | AI for Interoperability |
      | Base Registries         |
      | Data Spaces             |
      | Knowledge Hub           |
      | SEMIC Conference        |
      | Overview                |

    Given I go to "/collection/justice-law-and-security/solution/leos-open-source-software-editing-legislation"
    Then I should see the link "LEOS on code.europa.eu" in the "Quick links" region
    Then I should see the link "EIF Perspective" in the "Quick links" region
    Then I should see the link "Release history" in the "Quick links" region
    But I should see the following lines of text in the correct order in "Quick links":
      | LEOS on code.europa.eu |
      | EIF Perspective        |
      | Release history        |
