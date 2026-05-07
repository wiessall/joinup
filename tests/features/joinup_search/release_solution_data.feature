@api @solrRetrieveData
Feature: Releases should inherit data from their parent.

  Scenario: Releases should inherit the topics of their parent.
    Given the following "solution" content:
      | title      | topic                            | state     |
      | Solution 1 | Finance in EU, Supplier exchange | published |
      | Solution 2 | E-health, Demography             | published |
    And the following "release" content:
      | title     | solution   | state     |
      | Release 1 | Solution 1 | published |
      | Release 2 | Solution 2 | published |

    Then the "Release 1" "release" should have the following fields in the "published" index:
      # Check testing_terms.yml for the hierarchy. Parent topics are added
      # as well
      | topic | Economy and Welfare, Finance in EU, eGov, Supplier exchange |
    And the "Release 2" "release" should have the following fields in the "published" index:
      | topic | E-health Dpt., E-health, Social and Political, Demography |

    # The parent is "Info" for both of the following topics.
    Given the "topic" of "Solution 1" solution is changed to "Data gathering and processing, Statistics and Analysis"
    # The parent is "HR Dpt." for both of the following topics.
    And the "topic" of "Solution 2" solution is changed to "Employment and Support Allowance, HR"

    # Only the new values should be available.
    Then the "Release 1" "release" should have the following fields in the "published" index:
      | topic | Info, Data gathering and processing, Statistics and Analysis |
    And the "Release 2" "release" should have the following fields in the "published" index:
      | topic | HR Dpt., Employment and Support Allowance, HR |
