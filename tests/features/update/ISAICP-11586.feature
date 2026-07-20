@api @group-clone
Feature: Normalize custom page URL aliases mistranslated by TMGMT

  @update:joinup_tmgmt_deploy_201500
  @update:joinup_tmgmt_deploy_201501
  Scenario Outline: Test normalization.
    Given I am logged in as a user with the "moderator" role
    When I go to "<url>"
    Then the response status code should be 200

    Examples:
      | url                                                                                                             |
      | /bg/resheniya-za-operativna-svmestimostresheniya                                                                |
      | /de/leitlinien-kapitel-5-wie-wird-ein-solides-regierungs-und-verwaltungssystem-aufgebaut-bewertung-der          |
      | /pl/interoperacyjna-europa/wydarzenia                                                                           |
