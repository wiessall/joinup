@api @group-a
Feature:
  In order keep the users up to date with licences
  As a site moderator
  I need to be able to inform users whether the licence is deprecated or not.

  Scenario Outline: Show a deprecation warning depending on the deprecation flag.
    Given licence content:
      | title                    | description               | licence type  | deprecated    |
      | Maybe deprecated licence | Licence agreement details | Public domain | <deprecation> |
    When I visit the "Maybe deprecated licence" licence
    Then I <expected result> the warning message "This licence is deprecated and will not be selected for new distributions."

    Examples:
      | deprecation | expected result |
      | yes         | should see      |
      | no          | should not see  |
