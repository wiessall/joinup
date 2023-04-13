Third party services
====================

This application uses the following third party services:

- [Webtools analytics](#webtools-analytics)

Webtools analytics
------------------

The Webtools analytics service is used to collect anonymous usage statistics
about the application. The data is used to improve the application and to
monitor its performance.

The service is based to the [MATOMO](https://matomo.org/) open source analytics
platform. The documentation for the webtools analytics service can be found
[here](https://webgate.ec.europa.eu/fpfis/wikis/display/webtools/Europa+Analytics).

The pproduction environment is at the endpoint
[https://webanalytics.europa.eu/](https://webanalytics.europa.eu/). The
development environment is at the endpoint
[https://webanalytics.acc.fpfis.tech.ec.europa.eu/](https://webanalytics.acc.fpfis.tech.ec.europa.eu/).

In order to use the instance, the following environment variables must be set:
```dotenv
OE_WEBTOOLS_ANALYTICS_SITE_ID=
OE_WEBTOOLS_ANALYTICS_SITE_PATH=
OE_WEBTOOLS_ANALYTICS_SITE_INSTANCE=
```
The site ID is provided by the devops. The site path is the path of the site
where the analytics are collected. The site instance is the instance of the
site where the analytics are collected.

Additionally, the URL of your local instance must match the URL accepted by the
service. The development service, accepts the following URLs:
* digit-joinup.acc.fpfis.tech.ec.europa.eu

So, in order to use the development service, you must set the following in your
hosts file:
```hosts
<your local IP - usually 127.0.0.1> digit-joinup.acc.fpfis.tech.ec.europa.eu
```
For the site ID, please ask your team to provide you the hash. The site path is
the URL above and the site instance is `testing`.
