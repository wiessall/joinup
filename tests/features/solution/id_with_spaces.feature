@api @group-h
Feature: Solution API
  In order to be able to use solutions with spaces in their uris
  As a backend developer
  I need to be able to store them

  Scenario: Programmatically create a solution
    Given solution content:
      | uri                                    | title                | description       | logo     | documentation | content creation | landing page                   | webdav creation | webdav url                           | wiki                            | state     |
      | http://example.com/this%20has%20spaces | Solution with spaces | A sample solution | logo.png | text.pdf      | registered users | http://foo-example.com/landing | no              | http://joinup.eu/solution/foo/webdav | http://example.wiki/foobar/wiki | published |
    And solution content:
      | uri                                                                                                      | title                 | description       | logo     | documentation | content creation | landing page                   | webdav creation | webdav url                           | wiki                            | state     |
      | http://www.it-planungsrat.de/DE/Projekte/Koordinierungsprojekte/OsiP/Online_Sicherheitspr%C3%BCfung.html | Solution with unicode | A sample solution | logo.png | text.pdf      | registered users | http://foo-example.com/landing | no              | http://joinup.eu/solution/foo/webdav | http://example.wiki/foobar/wiki | published |
