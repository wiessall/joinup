@api @group-i
Feature: User profile
  A user must be able to change his own profile.
  A moderator must be able to edit any user account.

  Scenario: A logged-in user can navigate to his own profile and edit it.
    Given users:
      | Username             | E-mail              |
      | Leonardo Da Vinci    | foo@bar.com         |
      | Domenico Ghirlandaio | domedome@firenze.it |
    When I am logged in as "Leonardo Da Vinci"
    And I am on the homepage
    Then I click "My account"
    # If no first name and last name are given, the title should default to the
    # username.
    Then I should see the heading "Leonardo Da Vinci"
    And I should see the avatar "user_icon.png"

    But I should not see the text "Country of origin:" in the "Header" region
    And I should not see the link "View" in the "Entity actions" region

    When I click "Edit"
    Then the following fields should be present "Email, First name"
    And the following fields should be present "Family name, Photo, Country of origin, Site language, Professional domain, Business title"
    And the following fields should be present "Facebook, X, LinkedIn, GitHub, SlideShare, Youtube, Vimeo"
    And the following fields should not be present "Time zone"
    And the "Site language" select should contain the following options:
      | Bulgarian  |
      | Croatian   |
      | Czech      |
      | Danish     |
      | Dutch      |
      | English    |
      | Estonian   |
      | Finnish    |
      | French     |
      | German     |
      | Greek      |
      | Hungarian  |
      | Irish      |
      | Italian    |
      | Latvian    |
      | Lithuanian |
      | Maltese    |
      | Polish     |
      | Portuguese |
      | Romanian   |
      | Slovak     |
      | Slovenian  |
      | Spanish    |
      | Swedish    |
    And I should see the text "Username"
    And I should see the text "Leonardo Da Vinci"
    And I fill in "First name" with "Leoke"
    And I fill in "Family name" with "di ser Piero da Vinci"
    And I select "Supplier exchange" options from "Professional domain"
    And I fill in "Country of origin" with "Belgium"
    And I fill in "Facebook" with "leodavinci"
    And I fill in "X" with "therealdavinci"
    And I fill in "LinkedIn" with "leonardo.davinci"
    And I fill in "GitHub" with "davinci"
    And I fill in "Mastodon" with "myaccount.pl"

    # Verify that the business title is a field limited to 255 characters.
    When I fill in "Business title" with "Lorem ipsum dolor sit amet, consectetur adipiscing elit. Praesent sagittis justo ornare justo porta tristique vitae eu ligula. Mauris iaculis eros id nulla posuere, id luctus orci ultricies. Aenean at leo diam. Aliquam dapibus nibh et est pharetra, quis interdum"
    And I press "Save"
    Then I should see the inline form error message "Business title cannot be longer than 255 characters but is currently 262 characters long."

    When I fill in "Business title" with "Italian Renaissance polymath"
    And I press "Save"
    Then I should see the success message "The changes have been saved."
    And I should see the heading "Leoke di ser Piero da Vinci" in the "Header" region
    And I see the text "Italian Renaissance polymath" in the "Header" region
    And I should see the text "Supplier exchange"
    And I should see the link "Edit"
    And the link "Facebook" in the "Header" region should point to "https://www.facebook.com/leodavinci"
    And the link "X" in the "Header" region should point to "https://x.com/therealdavinci"
    And the link "LinkedIn" in the "Header" region should point to "https://www.linkedin.com/leonardo.davinci"
    And the link "GitHub" in the "Header" region should point to "https://github.com/davinci"
    And the link "Mastodon" in the "Header" region should point to "https://myaccount.pl"
    And I should not see the link "SlideShare" in the "Header" region
    And I should not see the link "Youtube" in the "Header" region
    And I should not see the link "Vimeo" in the "Header" region
    # @todo The nationality will be rendered as flag image.
    # @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-3175
    # And I should see the link "Belgium"
    # A user should not be able to edit the profile page of another user.
    When I go to the public profile of "Domenico Ghirlandaio"
    Then I should not see the link "Edit"
    # Verify that the user's "Country of origin" field is visible on its profile.
    When I go to the public profile of "Leonardo Da Vinci"
    Then I should see the text "Country of origin: Belgium" in the "Header" region

  Scenario: A moderator can navigate to any user's profile and edit it.
    Given users:
      | Username          | E-mail                | Roles     |
      | Leonardo Da Vinci | leonardo@example.com  |           |
      | Mighty mod        | moderator@example.com | moderator |
    When I am logged in as "Mighty mod"
    And I go to the homepage
    Then I click "People"
    Then I should be on "admin/people"
    Then I fill in "Name or email contains" with "Leonardo"
    And I press "Filter"
    Then I click "Leonardo Da Vinci"
    Then I click "Edit"
    Then the following fields should be present "Email, Username"
    And the following fields should be present "First name, Family name, Add a new file, Professional domain, Business title"
    And the following fields should be present "Country of origin, Organisation"
    And the following fields should not be present "Time zone"
    And I should not see the text "Username: Leonardo Da Vinci"
    And I fill in "First name" with "Leo"
    And I fill in "Family name" with "di ser Piero da Vinci"
    And I select "Finance in EU" options from "Professional domain"
    And I fill in "Country of origin" with "Belgium"
    And I press "Save"
    Then I should see the success message "The changes have been saved."
    And I should see the success message "The user has been notified that their account has been updated."
    And the following email should have been sent:
      | recipient | Leonardo Da Vinci                                                                                                              |
      | subject   | The Portal Support Team updated your account for you at Interoperable Europe Portal                                            |
      | body      | A moderator has edited your user profile on Interoperable Europe Portal. Please check your profile to verify the changes done. |

  Scenario: The user public profile page shows the content he's author of or is member of.
    Given users:
      | Username          | E-mail                        | First name | Family name |
      | Corwin Robert     | corwin.robert@example.com     |            |             |
      | Anise Edwardson   | anise.edwardson@example.com   |            |             |
      | Jayson Granger    | jayson.granger@example.com    |            |             |
      | Clarette Fairburn | clarette.fairburn@example.com | Clarette   | Fairburn    |
    And collection content:
      | title                 | description                           | logo     | creation date    |
      | Botanic E.D.E.N.      | European Deep Earth Nurturing project | logo.png | 2017-02-23 10:00 |
      | Ethic flower handling | Because even flowers have feelings.   | logo.png | 2017-02-23 12:00 |
    Given solution content:
      | title              | collection            | description                                     | logo     | creation date    |
      | E.C.O. fertilizers | Botanic E.D.E.N.      | Ecologic cool organic fertilizers production.   | logo.png | 2017-02-23 13:00 |
      | SOUND project      | Ethic flower handling | Music playlist for growing flowers with rhythm. | logo.png | 2017-02-23 14:01 |
    And discussion content:
      | title                          | author          | collection            | created          |
      | Repopulating blue iris         | Corwin Robert   | Botanic E.D.E.N.      | 2018-06-15 16:00 |
      | Best topsoil for plant comfort | Anise Edwardson | Ethic flower handling | 2018-09-01 19:30 |
    And document content:
      | title                    | author        | collection       | created          |
      | Cherry blossoms schedule | Corwin Robert | Botanic E.D.E.N. | 2017-05-13 16:00 |
    And event content:
      | title                | author        | collection       | created          |
      | Spring blossom party | Corwin Robert | Botanic E.D.E.N. | 2018-06-27 18:00 |
    And news content:
      | title                         | author        | collection       | created         |
      | Discovered new flower species | Corwin Robert | Botanic E.D.E.N. | 2018-11-15 9:01 |
    And the following collection user membership:
      | user          | collection       |
      | Corwin Robert | Botanic E.D.E.N. |
    And the following solution user membership:
      | user          | solution      |
      | Corwin Robert | SOUND project |

    When I am an anonymous user
    And I go to the public profile of "Corwin Robert"
    Then I should see the heading "Corwin Robert"
    # Tiles should be shown for the groups the user is member of or author of.
    Then I should see the following tiles in the correct order:
      | Discovered new flower species |
      | Spring blossom party          |
      | Repopulating blue iris        |
      | Cherry blossoms schedule      |
      | SOUND project                 |
      | Botanic E.D.E.N.              |
      # Always present in all tests.
      # See joinup_test.install
      | Portal support                   |
    And I should see the following facet items "Collections, Discussion, Document, Event, News, Solution" in this order

  Scenario: The user profile page title should show the full name of the user.
    Given users:
      | Username   | E-mail                       | First name | Family name |
      | cgarnett67 | callista.garnett@example.com | Callista   | Garnett     |
      | delwin999  | deforest.elwin@example.com   |            |             |

    # When the user has filled first and family name, the profile should show
    # the full name as header title and in the page title tag.
    When I go to the public profile of cgarnett67
    Then I should see the heading "Callista Garnett" in the "Header" region
    And the HTML title of the page should be "Callista Garnett"
    # The title should not be duplicated.
    And I should not see the "Page title" region
    And I should not see the heading "cgarnett67"

    # The full name fall backs to the user name when the fields are not filled.
    When I go to the public profile of delwin999
    Then I should see the heading delwin999 in the "Header" region
    And the HTML title of the page should be delwin999
    And I should not see the "Page title" region

  Scenario: The user profile page is updated when the user joins or leaves a collection
    Given users:
      | Username      | E-mail                           |
      | Korben Dallas | k.dallas@cabs.services.zorg.corp |
    And collection content:
      | title                   | state     |
      | Federated Army Veterans | published |
    # Visit the user profile page for the first time. It should not yet be
    # cached and we should not see anything about the collection since we are
    # not a member.
    When I am an anonymous user
    And I go to the public profile of "Korben Dallas"
    Then I should not see the "Federated Army Veterans" tile
    And the page should not be cached

    # On the next visit the page should be cached.
    When I reload the page
    Then the page should be cached

    # Join the collection. Now the cache of the user profile page should be
    # cleared and the collection that was joined should show up.
    Given I am logged in as "Korben Dallas"
    And I go to the homepage of the "Federated Army Veterans" collection
    And I press "Join this collection"

    When I am an anonymous user
    And I go to the public profile of "Korben Dallas"
    Then I should see the "Federated Army Veterans" tile
    And the page should not be cached

    # Verify the page can be cached correctly.
    When I reload the page
    Then the page should be cached

    # Leave the collection. Now the cache of the user profile page should be
    # cleared and the collection that was left should no longer show up.
    Given I am logged in as "Korben Dallas"
    And I go to the homepage of the "Federated Army Veterans" collection
    And I click "Leave this collection"
    And I press "Confirm"

    When I am an anonymous user
    And I go to the public profile of "Korben Dallas"
    Then I should not see the "Federated Army Veterans" tile
    And the page should not be cached

    # Verify the page can be cached correctly.
    When I reload the page
    Then the page should be cached

  Scenario: An authenticated user should not have access to restricted pages of his profile.
    When I am logged in as an "authenticated user"
    And I am on the homepage
    And I click "My account"
    Then I should not see the link "Subscription settings"
    And I should not see the link "Persistent Logins"

  Scenario: See archived content in user profile.
    Given users:
      | Username          | E-mail                        |
      | Janny Robert      | janny.robert@example.com      |
      | Clarise Edwardson | clarise.edwardson@example.com |
    And collection content:
      | title         | description                           | logo     | state     | creation date    | author            |
      | Lake E.D.E.N. | European Deep Earth Nurturing project | logo.png | published | 2017-02-23 10:00 | Janny Robert      |
      | Rose is red   | Better descriptions ever              | logo.png | published | 2017-02-23 10:00 | Clarise Edwardson |
      | Lone handling | Because even flowers have feelings.   | logo.png | archived  | 2017-02-23 12:00 | Janny Robert      |
    Given solution content:
      | title          | collection    | state     | creation date   | author       |
      | Sugar is sweet | Lake E.D.E.N. | archived  | 2015-03-03T8:00 | Janny Robert |
      | Salt is salty  | Lake E.D.E.N. | published | 2015-02-03T7:00 | Janny Robert |
    And discussion content:
      | title                   | author       | collection    | state     | created          |
      | Repopulating green iris | Janny Robert | Lake E.D.E.N. | published | 2018-06-15 16:00 |
      | Best plant comfort      | Janny Robert | Lone handling | published | 2018-09-01 19:30 |
    And document content:
      | title           | author       | collection    | state     | created          |
      | Cherry schedule | Janny Robert | Lake E.D.E.N. | published | 2017-05-13 16:00 |
      | Cherry arch     | Janny Robert | Lake E.D.E.N. | archived  | 2017-05-13 16:00 |
    And news content:
      | title             | author       | collection    | state     | created         |
      | Discovered flower | Janny Robert | Lake E.D.E.N. | published | 2018-11-15 9:01 |
      | Purple flower     | Janny Robert | Lake E.D.E.N. | published | 2018-11-15 9:01 |
      | Blue flower       | Janny Robert | Lake E.D.E.N. | archived  | 2018-11-15 9:01 |

    When I am an anonymous user
    And I go to the public profile of "Clarise Edwardson"
    Then I should see the heading "Clarise Edwardson"
    And I should see the text "Show archived"
    When I click "Show archived"
    And I should see 0 tiles

    When I am an anonymous user
    And I go to the public profile of "Janny Robert"
    Then I should see the heading "Janny Robert"
    And I should see the text "Show archived"
    Then I should see the following tiles in the correct order:
      | Discovered flower       |
      | Purple flower           |
      | Best plant comfort      |
      | Repopulating green iris |
      | Cherry schedule         |
      | Lake E.D.E.N.           |
      | Salt is salty           |
      # Always present in all tests.
      # See joinup_test.install
      | Portal support             |

    When I click "Show archived"
    Then I should see the following tiles in the correct order:
      | Blue flower    |
      | Cherry arch    |
      | Lone handling  |
      | Sugar is sweet |
    And I should see the text "Archived" in the "Blue flower" tile
    And I should see the text "Archived" in the "Cherry arch" tile
    And I should see the text "Archived" in the "Lone handling" tile
    And I should see the text "Archived" in the "Sugar is sweet" tile

  @javascript
  # Regression test for ticket ISAICP-9586.
  Scenario: Search widget does not save facet settings.
    Given users:
      | Username         | E-mail                       | First name | Family name |
      | Lion Robert      | lion.robert@example.com      | Lion       | Robert      |
    When I am logged in as a moderator
    And I click "People"
    And I click "Lion Robert"
    And I open the header local tasks menu
    And I click "Edit" in the "Entity actions" region
    And I should see the button "Show row weights"
    Then I drag the "Status" table row up
    And I press "Save"
    # Check if was saved correctly.
    And I open the header local tasks menu
    And I click "Edit" in the "Entity actions" region
    And I should see the button "Show row weights"
    Then the search api widget table should be:
      | title  | type                   | weight |
      | Status | Collection content top | -10    |
      | Type   | Collection content top | -9     |

    When I press "Show row weights"
    And I should see the button "Hide row weights"
    Then I drag the "Status" table row down
    And I press "Save"

    And I open the header local tasks menu
    And I click "Edit" in the "Entity actions" region
    And I should not see the button "Show row weights"
    And I should see the button "Hide row weights"
    Then the search api widget table should be:
      | title  | type                   | weight |
      | Type   | Collection content top | -10    |
      | Status | Collection content top | -9     |

  # Regression test for ticket ISAICP-10510.
  Scenario: User profile with orphaned nationality reference does not cause errors
    Given spatial terms:
      | name    |
      | Valeron |
    Given users:
      | Username      | E-mail                    | First name | Family name | Business title      | Nationality |
      | Jane Doe      | jane.doe@example.com      | Jane       | Doe         | Software Engineer   | Valeron     |
    # Delete the term we just created, leaving a stale reference in the user.
    And I delete the "Spatial" term "Valeron"

    # Visit search results where users are rendered in tile view mode
    # This is where the bug would occur - in ventuno_preprocess_user() for tile view modes
    When I am an anonymous user
    And I visit "/search?f[0]=type:user&text=Jane"
    # The search should complete without errors even though Jane has an orphaned nationality
    Then I should see the text "Jane Doe"
    And I should see the text "Software Engineer"
    # The nationality should not be displayed since the referenced entity is gone
    But I should not see the text "Country of origin:"

  Scenario: The URL determines the interface language and login redirects to the user preferred language.
    Given users:
      | Username      | E-mail             | First name | Family name |
      | Pierre Martin | pierre@example.com | Pierre     | Martin      |
    When I am logged in as "Pierre Martin"
    And I visit "/user/edit"
    Then the "lang" attribute of the page should be "en"
    And I select "French" from "Site language"
    And I press "Save"

    When I am an anonymous user
    Then the "lang" attribute of the page should be "en"

    # After login, the user is redirected to their preferred language.
    When I am logged in as "Pierre Martin"
    Then the "lang" attribute of the page should be "fr"

    When I visit "/pt/user"
    Then the "lang" attribute of the page should be "pt"
