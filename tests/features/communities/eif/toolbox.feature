@api @eif_community @group-e
Feature:
  As the owner of the EIF toolbox
  in order to make it easier for users to find appropriate solutions
  I need to be able to present them in a nice searchable way.

  @javascript
  Scenario: Present and search the solutions.
    Given owner:
      | name | type    |
      | ACDC | Company |
    And collections:
      | title   | state     |
      | Killjoy | published |
      | Sage    | published |
      | Phoenix | draft     |
    And solutions:
      | title    | eif reference                      | eif category    | collection | state     |
      | Jordan 1 | Recommendation 1, Recommendation 2 | Common services | Killjoy    | published |
      | Jordan 2 | Recommendation 4                   | Common services | Sage       | published |
      | Jordan 3 | Recommendation 5, Recommendation 2 | Common services | Sage       | draft     |
      | Jordan 4 | Recommendation 3, Recommendation 1 | Common services | Phoenix    | published |
      | Jordan 5 | Recommendation 4                   | Common services | Killjoy    | draft     |
      | Jordan 6 | Recommendation 4                   | Common services | Killjoy    | published |
    And glossary content:
      | title        | synonyms | definition  | collection      |
      | amazing      | SMASS    | amazing def | NIFO collection |
      | It will be   |          | survival    | Killjoy         |

    # Visit the toolbox page once to ensure that solutions are properly
    # re-indexed when an EIF perspective is created or deleted and that
    # cache is invalidated properly.
    Given I visit the "Online Assistant" custom page
    Then I should not see the following headings:
      | Jordan 1 |
      | Jordan 2 |
      | Jordan 3 |
      | Jordan 4 |
      | Jordan 5 |

    Given the following EIF perspective data for the "Jordan 1" solution:
      | Description      | It will be an amazing principle.                                                                                     |
      | Type of solution | Common services                                                                                                      |
      | Solution type    | Assessment tools, Generic tools                                                                                      |
      | Layer            | Interoperability Layer 3: Legal interoperability                                                                     |
      | Model            | Basic Component 1: Coordination function                                                                             |
      | Principle        | Underlying Principle 2: Openness, Underlying Principle 3: Transparency, Underlying Principle 8: Security and privacy |
    And the following EIF perspective data for the "Jordan 2" solution:
      | Description   | It will be an amazing principle.                 |
      | Solution type | Assessment tools, Generic tools                  |
      | Layer         | Interoperability Layer 3: Legal interoperability |
      | Model         | Basic Component 1: Coordination function         |
      | Principle     | Underlying Principle 3: Transparency             |
    And the following EIF perspective data for the "Jordan 3" solution:
      | Description   | It will be an amazing principle.                      |
      | Solution type | Common services                                       |
      | Principle     | Underlying Principle 11: Preservation of information  |
      | Layer         | Interoperability Layer 1: Interoperability governance |
      | Model         | Basic Component 4: Open data                          |
    And the following EIF perspective data for the "Jordan 4" solution:
      | Description   | It will be an amazing principle.                      |
      | Solution type | Common services                                       |
      | Principle     | Underlying Principle 11: Preservation of information  |
      | Layer         | Interoperability Layer 1: Interoperability governance |
      | Model         | Basic Component 4: Open data                          |
    # Regression test for ticket ISAICP-7303.
    And the following EIF perspective data for the "Jordan 6" solution:
      | Description   | <p>This is v1.0.0 of the <strong>SMASS</strong> survey.</p> |
      | Solution type | Common services                                             |
      | Principle     | Underlying Principle 11: Preservation of information        |
      | Layer         | Interoperability Layer 1: Interoperability governance       |
      | Model         | Basic Component 4: Open data                                |

    When I visit the "Online Assistant" custom page
    And I should see the text "The EIF Solutions page counts with an online assistant that helps users to find operational solutions covering the alignment and implementation of the European Interoperability Framework and filter them by attributes."
    And I should see the text "Step 1 - Choose one or combine multiple filters to find solutions that match specifically your EIF requirements"
    And I should see the text "Step 2 - Explore operational solutions that help align to the European Interoperability Framework"
    And I should see the text "This is v1.0.0 of the SMASS survey."
    And the response should contain "/collection/nifo-collection/glossary/term/amazing\" class=\"glossary-term\" title=\"amazing def\">SMASS</a>"

    And I should see the following filter categories in the correct order:
      | Solution category       |
      | Underlying principles   |
      | Interoperability layers |
      | Conceptual model        |
    And I should see the following headings:
      | Jordan 1 |
      | Jordan 2 |
      | Jordan 4 |
      | Jordan 6 |
    But I should not see the following headings:
      | Jordan 3 |
      | Jordan 5 |

    # Test the principles.
    When I press "Transparency" in the "Content" region
    Then I should see the following headings:
      | Jordan 1 |
      | Jordan 2 |
    And I should see the text "2 matching solutions found"
    But I should not see the following headings:
      | Jordan 3 |
      | Jordan 4 |
      | Jordan 5 |
      | Jordan 6 |

    # Test multiple options. Both "Transparency" and "Security and privacy" are
    # selected.
    When I press "Security and privacy" in the "Content" region
    Then I should see the following headings:
      | Jordan 1 |
    And I should see the text "1 matching solutions found"
    But I should not see the following headings:
      | Jordan 2 |
      | Jordan 3 |
      | Jordan 4 |
      | Jordan 5 |
      | Jordan 6 |

    # Reset the options.
    When I press "Transparency" in the "Content" region
    When I press "Security and privacy" in the "Content" region
    Then I should see the following headings:
      | Jordan 1 |
      | Jordan 2 |
      | Jordan 4 |
      | Jordan 6 |
    And I should see the text "4 matching solutions found"
    But I should not see the following headings:
      | Jordan 3 |
      | Jordan 5 |

    # Test the interoperability layer.
    When I press "Legal interoperability" in the "Content" region
    Then I should see the following headings:
      | Jordan 1 |
      | Jordan 2 |
    But I should not see the following headings:
      | Jordan 3 |
      | Jordan 4 |
      | Jordan 5 |
      | Jordan 6 |

    # Test selecting terms from more than one vocabularies.
    When I press "Openness" in the "Content" region
    Then I should see the following headings:
      | Jordan 1 |
    But I should not see the following headings:
      | Jordan 2 |
      | Jordan 3 |
      | Jordan 4 |
      | Jordan 5 |
      | Jordan 6 |

    # Reset the options.
    When I press "Openness" in the "Content" region
    When I press "Legal interoperability" in the "Content" region
    Then I should see the following headings:
      | Jordan 1 |
      | Jordan 2 |
      | Jordan 4 |
      | Jordan 6 |
    But I should not see the following headings:
      | Jordan 3 |
      | Jordan 5 |

    # Test the conceptual model.
    When I press "Open data" in the "Content" region
    Then I should see the following headings:
      | Jordan 4 |
      | Jordan 6 |
    But I should not see the following headings:
      | Jordan 1 |
      | Jordan 2 |
      | Jordan 3 |
      | Jordan 5 |

    # Reset the options.
    When I press "Open data" in the "Content" region
    Then I should see the following headings:
      | Jordan 1 |
      | Jordan 2 |
      | Jordan 4 |
      | Jordan 6 |
    But I should not see the following headings:
      | Jordan 3 |
      | Jordan 5 |

    # Test the solution category.
    When I press "Assessment tools" in the "Content" region
    Then I should see the following headings:
      | Jordan 1 |
      | Jordan 2 |
    Then I should not see the following headings:
      | Jordan 3 |
      | Jordan 4 |
      | Jordan 5 |
      | Jordan 6 |

    # Reset the options.
    When I press "Assessment tools" in the "Content" region
    Then I should see the following headings:
      | Jordan 1 |
      | Jordan 2 |
      | Jordan 4 |
      | Jordan 6 |
    But I should not see the following headings:
      | Jordan 3 |
      | Jordan 5 |

    # Ensure glossary words style applied
    When I visit the "Online Assistant" custom page
    # This glossary term doesn't show because we are in NIFO collection.
    # When we go inside the EIF perspective page we should see the 'It will be' glossary link.
    And I should not see the link "It will be"
    And I should see the link "amazing"
    And the response should contain "/collection/nifo-collection/glossary/term/amazing\" class=\"glossary-term\" title=\"amazing def\">amazing</a>"
    And I should see the link "SMASS"
    And the response should contain "/collection/nifo-collection/glossary/term/amazing\" class=\"glossary-term\" title=\"amazing def\">SMASS</a></strong> survey.</p>"

    When I go to the EIF perspective page of the "Jordan 6" solution
    And I should not see the link "It will be"
    And I should not see the link "amazing"
    And I should not see the link "SMASS"

    When I go to the EIF perspective page of the "Jordan 1" solution
    And I should see the link "It will be"
    And I should not see the link "amazing"
    And I should not see the link "SMASS"

    # Ensure that deleting an EIF perspective, the online assistant is updated
    # as well.
    Given I delete the EIF perspective of the "Jordan 1" solution
    When I visit the "Online Assistant" custom page
    And I should not see the heading "Jordan 1"
