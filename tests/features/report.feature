@api
Feature: Report inappropriate content
  In order to outsource the discovery of inappropriate content on the site
  As a moderator
  I want to provide a "Report" button on community content

  Scenario Outline: Report inappropriate community content
    Given collection content:
      | title            | state     |
      | Deployment tools | published |
    And "<type>" content:
      | title   | body   | collection       | state   |
      | <title> | <body> | Deployment tools | <state> |
    Given I am not logged in
    When I go to the content page of the type "<type>" with the title "<title>"
    And I click "Report"
    Then I should see the heading "Contact"
    And the "Category" field should contain "report"
    And the "Page URL" field should contain the link to the "<title>" page

    # Submit the form to check if 'destination' has been correctly set.
    And I fill in the following:
      | First name     | John            |
      | Last name      | Doe             |
      | Organisation   |                 |
      | E-mail address | doe@example.rg  |
      | Subject        | Invalid content |
      | Message        | Spam            |
    When I press "Submit"
    Then I should see the heading "<title>"

    Examples:
      | type        | title                        | body                    | state     |
      | discussion  | Git is not a deployment tool | Use tarballs to deploy. | published |
      | custom_page | The best tools               | Current best-in-class.  | published |
      | news        | Now deploying to containers  | Long awaited feature.   | published |
      | document    | Deployment strategies        | Deploy faster.          | published |
      | event       | GovDeploy Bootcamp 2017      | Submit your session.    | published |
