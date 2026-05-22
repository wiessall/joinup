@api @group-h
Feature: Solution API
  In order to be able to use solutions with spaces in their uris
  As a backend developer
  I need to be able to store them

  Scenario: Programmatically create a solution
    Given the following "solution" content:
      | uri                                    | title                | content_creation | landing page                   |
      | http://example.com/this%20has%20spaces | Solution with spaces | registered users | http://foo-example.com/landing |
    And the following "solution" content:
      | uri                                                                                                      | title                 | content_creation | landing page                   |
      | http://www.it-planungsrat.de/DE/Projekte/Koordinierungsprojekte/OsiP/Online_Sicherheitspr%C3%BCfung.html | Solution with unicode | registered users | http://foo-example.com/landing |
