# GitHub Platform Support

Allows the `hosting_platform` provider plugin to federate data from GitHub
repositories.

## Configuration

GitHub API calls are authenticated. You should obtain an access token and store
it in the `settings.php` file:

```php
$settings['eu_oss_catalogue']['platform_github']['access_token'] = '...';
```
