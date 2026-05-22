@api @group-i
Feature:
  As a user of the website
  When I go to the overview page of an EIRA term
  I want to be able to see if a term is deprecated.

  Scenario: Show deprecated terms without replacement.
    Given the following "eira" terms:
      | uri                                        | name       | description        | field_taxonomy_deprecated |
      | http://joinup.eu/eira/term/only-deprecated | Deprecated | This is deprecated | 1                         |

    When I am logged in as a moderator
    And I am on the homepage
    And I click "RDF URI converter"
    Then I should see the heading "Entity URI to URL and the other way around"

    When I fill in "RDF URI or entity URL" with "http://joinup.eu/eira/term/only-deprecated"
    And I press "Go!"
    Then I should see the heading "Deprecated"
    And I should see the warning message "This building block is deprecated, and should not be used in new development."
    But I should not see the warning message containing "Consider using"
    # Ensure caching.
    When I reload the page
    Then I should see the warning message "This building block is deprecated, and should not be used in new development."
    But I should not see the warning message containing "Consider using"

  Scenario: Show deprecated terms with a replacement.
    Given the following "eira" terms:
      | uri                                                 | name            | description           | field_taxonomy_deprecated | field_taxonomy_replaces |
      | http://joinup.eu/eira/term/deprecated-w-replacement | DeprecatedTerm  | This is deprecated    | 1                         |                         |
      | http://joinup.eu/eira/term/replacement              | ReplacementTerm | This is a replacement | 0                         | DeprecatedTerm          |
    When I am logged in as a moderator
    And I am on the homepage
    And I click "RDF URI converter"
    And I fill in "RDF URI or entity URL" with "http://joinup.eu/eira/term/deprecated-w-replacement"
    And I press "Go!"
    Then I should see the heading "DeprecatedTerm"
    And I should see the warning message "This building block is deprecated, and should not be used in new development. Consider using ReplacementTerm (http://joinup.eu/eira/term/replacement) instead."
    # Ensure caching.
    When I reload the page
    Then I should see the warning message "This building block is deprecated, and should not be used in new development. Consider using ReplacementTerm (http://joinup.eu/eira/term/replacement) instead."
