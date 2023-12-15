@@api @group-clone
Feature: Post update tests for ISAICP-8329.

  Scenario Outline: Redirect from About to Overview.
    Then I visit "<about>"
    And I should be on "<page>"

    Examples:
      | about                                                                                                          | page                                                                                                     |
      | collection/eupl/about                                                                                          | collection/eupl                                                                                          |
      | collection/joinup/about                                                                                        | collection/joinup                                                                                        |
      | collection/european-committee-standardization-cen/solution/european-culturally-specific-ict-requirements/about | collection/european-committee-standardization-cen/solution/european-culturally-specific-ict-requirements |
      | collection/european-committee-standardization-cen/solution/dublin-core-egovernment-application-profiles/about  | collection/european-committee-standardization-cen/solution/dublin-core-egovernment-application-profiles  |
