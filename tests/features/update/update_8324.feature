@api @group-clone
Feature: Post update tests for ISAICP-8324.

  Scenario Outline: Disable 'Members' and "About" menu items.
    Then I visit "<page>"
    When I should not see the link "Members" in the "Navigation menu"
    And I should not see the link "About" in the "Navigation menu"

    Examples:
      | page                                                                                                       |
      | collection/eupl                                                                                            |
      | collection/joinup                                                                                          |
      | collection/nifo-national-interoperability-framework-observatory/digital-public-administration-factsheets   |
      | collection/european-committee-standardization-cen/solution/standardisation-online-dispute-resolution-tools |


  Scenario Outline: Disable 'EIF Perspective" menu items.
    Then I visit "<page>"
    When I should not see the link "EIF Perspective" in the "Navigation menu"

    Examples:
      | page                                                                                    |
      | collection/interoperability-maturity-tools-imts-digital-public-services/solution/oimaps |
      | collection/joinup/solution/joinup-source-code                                           |
