# OE NEWSROOM NEWSLETTER
This module provides integration with the newsletter service.

### Setup
In order to set up the service, copy the `OE_NEWSROOM_UNIVERSE_KEYS` key from
the `.env.dist` over to `.env` and configure it with the key provided by the
service.

In order to verify if the settings are applied, check
`PROJECT_ROOT/resources/runner/drupal.yml`. The `OpenEuropa Newsroom
Newsletter Settings` is setting up the environment variable in `settings.php`.

### Usage
The module is providing a field type, widget and formatter to show a
subscription form in an entity. By trying to subscribe to the service, the
`\Drupal\oe_newsroom_newsletter\NewsletterSubscriber\NewsletterSubscriber`
is used to contact the service.

### Response
The current service returns a `Response` object with one `body` object for each
email attempting to subscribe. Each `body` object has the following properties.

| Property name | Value type |
| --- | --- |
| responseType | "json" |
| email | string |
| firstName | string - can be null |
| lastName | string - can be null |
| organisation | string - can be null |
| country | string - can be null |
| position | string - can be null |
| twitter | string - can be null |
| facebook | string - can be null |
| linkedIn | string - can be null |
| organisationShort | string - can be null |
| address | string - can be null |
| address2 | string - can be null |
| postCode | string - can be null |
| city | string - can be null |
| department | string - can be null |
| media | string - can be null |
| website | string - can be null |
| role | string - can be null |
| universeId | string |
| universeName | string |
| universAcronym | string |
| newsletterId | string |
| newsletterName | string |
| status | "Valid" or "Invalid" |
| unsubscriptionLink | URL as a string |
| isNewUser | string - can be null |
| hostBy | string |
| profileLink | string |
| isNewSubscription | boolean |
| feedbackMessage | string |
| language | string - can be "default" |
| frequency | string - defaults to "On demand" |
| defaultLanguage | string - can be "0" (as a string) |
| pattern | string - can be null |

### Testing
For testing purposes, the module provides a MockNewsletterSubscriber which can
be enabled and are enabled in tests according to the "OpenEuropa Newsroom
Newsletter Mock" in `drupal.yml`'s settings setup.
