# Table of contents
- [Table of contents](#table-of-contents)
- [Description](#description)
- [Installtion](#installtion)
- [Version Support](#version-support)
- [HTTP Client Configuration](#http-client-configuration)
- [Example](#example)
- [Usage](#usage)
  - [Get authorization link](#get-authorization-link)
  - [Get token credentials](#get-token-credentials)
  - [Get Garmin user id](#get-garmin-user-id)
  - [Backfill activities](#backfill-activities)
  - [Deregistration](#deregistration)
  - [Avalaible methods (for the moment)](#avalaible-methods-for-the-moment)
    - [Get summary activities](#get-summary-activities)
    - [Backfill activities](#backfill-activities-1)

# Description
PHP library to connect and use garmin wellness api

# Version Support
This library now supports both **Chinese** and **International** versions of Garmin Connect API:

- **International Version** (default): Uses `connectapi.garmin.com` and `healthapi.garmin.com`
- **Chinese Version**: Uses `connectapi.garmin.cn` (OAuth) and `gcs-wellness.garmin.cn` (Health REST API)

## Version Usage Examples

### Creating API instances for different versions
```php
use Stoufa\GarminApi\GarminApi;

$config = [
    'identifier' => getenv('GARMIN_KEY'),
    'secret' => getenv('GARMIN_SECRET'),
    'callback_uri' => getenv('GARMIN_CALLBACK_URI')
];

// International version (default)
$internationalApi = new GarminApi($config, GarminApi::VERSION_INTERNATIONAL);

// Chinese version
$chineseApi = new GarminApi($config, GarminApi::VERSION_CHINESE);
```

### Dynamic version switching
```php
$api = new GarminApi($config);

// Switch to Chinese version
$api->useChineseVersion();

// Switch to International version
$api->useInternationalVersion();

// Get current configuration
echo "Current version: " . $api->getVersion();
echo "API URL: " . $api->getApiUrl();
echo "User API URL: " . $api->getUserApiUrl();
echo "Auth URL: " . $api->getAuthUrl();
```

### Version-specific endpoints
- **Chinese Version**:
  - OAuth API URL: `https://connectapi.garmin.cn/` (for request/access token)
  - Health REST API URL: `https://gcs-wellness.garmin.cn/wellness-api/rest/` (for activities and user data)
  - Auth URL: `http://connect.garmin.cn/oauthConfirm`
  - Tools URL: `https://healthtools.garmin.cn/tools/login` (Data Viewer / Backfill / Ping)

- **International Version**:
  - API URL: `https://connectapi.garmin.com/`
  - User API URL: `https://healthapi.garmin.com/wellness-api/rest/`
  - Auth URL: `http://connect.garmin.com/oauthConfirm`

### Backward Compatibility
Existing code will continue to work without any changes, as the international version is used by default.

# HTTP Client Configuration

This library supports custom HTTP client configuration to handle various network scenarios, including proxy settings, timeouts, SSL verification, and custom headers.

## Configuration Options

You can configure the HTTP client using any of the following Guzzle HTTP client options:

- `timeout` - Request timeout in seconds (default: 30)
- `connect_timeout` - Connection timeout in seconds (default: 10)
- `proxy` - Proxy server configuration
- `verify` - SSL certificate verification (default: true)
- `headers` - Default headers to apply to all requests
- `allow_redirects` - Controls redirect behavior
- `cookies` - Cookie jar configuration
- `debug` - Enable debug output

## Usage Examples

### 1. Configure via constructor

```php
use Stoufa\GarminApi\GarminApi;

$config = [
    'identifier' => getenv('GARMIN_KEY'),
    'secret' => getenv('GARMIN_SECRET'),
    'callback_uri' => getenv('GARMIN_CALLBACK_URI')
];

$httpConfig = [
    'timeout' => 60,
    'connect_timeout' => 15,
    'proxy' => 'http://proxy.example.com:8080',
    'verify' => false,
    'headers' => [
        'User-Agent' => 'My Custom App/1.0'
    ]
];

$server = new GarminApi($config, GarminApi::VERSION_INTERNATIONAL, $httpConfig);
```

### 2. Configure using setter method

```php
$server = new GarminApi($config);
$server->setHttpClientConfig([
    'timeout' => 45,
    'proxy' => 'http://proxy.example.com:8080',
    'headers' => [
        'User-Agent' => 'My Custom App/1.0'
    ]
]);
```

### 3. Get current configuration

```php
$currentConfig = $server->getHttpClientConfig();
print_r($currentConfig);
```

### 4. Common use cases

#### Set custom timeout for slow networks
```php
$server->setHttpClientConfig([
    'timeout' => 120,
    'connect_timeout' => 30
]);
```

#### Configure proxy server
```php
$server->setHttpClientConfig([
    'proxy' => 'http://username:password@proxy.example.com:8080'
]);
```

#### Disable SSL verification (for development)
```php
$server->setHttpClientConfig([
    'verify' => false
]);
```

#### Set custom User-Agent
```php
$server->setHttpClientConfig([
    'headers' => [
        'User-Agent' => 'MyApp/1.0 (contact@example.com)'
    ]
]);
```

#### Enable debug mode
```php
$server->setHttpClientConfig([
    'debug' => true
]);
```

All HTTP requests made by the library (OAuth authentication, API calls, etc.) will use the custom configuration.

# Installtion
```
composer require jinchun/php-garmin-connect-api
```
# Example

Please take a look at [examples](./examples/README.md) folder for complete usage examples, including [version switching examples](./examples/version_switching_example.php).

# Usage 
## Get authorization link
```php
use Stoufa\GarminApi\GarminApi;

try
{

    $config = array(
        'identifier'     => getenv('GARMIN_KEY'),
        'secret'         => getenv('GARMIN_SECRET'),
        'callback_uri'   => getenv('GARMIN_CALLBACK_URI') 
    );

    $server = new GarminApi($config);

    // Retreive temporary credentials from server 
    $temporaryCredentials = $server->getTemporaryCredentials();

    // Save temporary crendentials in session to use later to retreive authorization token
    $_SESSION['temporaryCredentials'] = $temporaryCredentials;

    // Get authorization link 
    $link = $server->getAuthorizationUrl($temporaryCredentials);
}
catch (\Throwable $th)
{
    // catch your exception here
}

```
## Get token credentials

After the user connects his garmin account successfully it will redirect to callback_uri. "oauth_token" and "oauth_verifier" should be available in $_GET. 

```php
try
{
    $config = array(
        'identifier'     => getenv('GARMIN_KEY'),
        'secret'         => getenv('GARMIN_SECRET'),
        'callback_uri'   => getenv('GARMIN_CALLBACK_URI') 
    );

    $server = new GarminApi($config);

    // Retrieve the temporary credentials we saved before
    $temporaryCredentials = $_SESSION['temporaryCredentials'];

    // We will now retrieve token credentials from the server.
    $tokenCredentials = $server->getTokenCredentials($temporaryCredentials, $_GET['oauth_token'], $_GET['oauth_verifier']);

}
catch (\Throwable $th)
{
    // catch your exception here
}
```

## Get Garmin user id

```php
$userId = $server->getUserUid($tokenCredentials);
```

## Backfill activities

When you connect garmin account and get token credentials first time, you won't be able to get previous activities because garmin does not give you activities older than your token credentials. Instead you need to use backfill method to fullfull your token with previous activities (no more than one month).


```php
// backfill activities for last 7 days ago
$params = [
    'summaryStartTimeInSeconds' => strtotime("-7 days", time()),
    'summaryEndTimeInSeconds' => time()
];
$server->backfillActivitySummary($tokenCredentials, $params);
```
## Deregistration
```php
$server->deleteUserAccessToken($tokenCredentials);
```

## Avalaible methods (for the moment)

### Get summary activities
```php
$params = [
    'uploadStartTimeInSeconds' => 1598814036, // time in seconds utc
    'uploadEndTimeInSeconds' => 1598900435 // time in seconds utc
];

// Activity summaries
$server->getActivitySummary($tokenCredentials, $params);

// Manually activity summaries
$server->getManuallyActivitySummary($tokenCredentials, $params);

// Activity details summaries
$server->getActivityDetailsSummary($tokenCredentials, $params);

// User metrics (including VO2 max and fitness age)
$server->getUserMetrics($tokenCredentials, $params);
```


### Backfill activities
```php
// For backfill params can be with upload start time
$params = [
    'uploadStartTimeInSeconds' => 1598814036, // time in seconds utc
    'uploadEndTimeInSeconds' => 1598900435 // time in seconds utc
];
// or with summary start time
$params = [
    'summaryStartTimeInSeconds' => 1598814036, // time in seconds utc
    'summaryEndTimeInSeconds' => 1598900435 // time in seconds utc
];

// Backfill activity summaries
$server->backfillActivitySummary($tokenCredentials, $params);

// Backfill daily activity summaries
$server->backfillDailySummary($tokenCredentials, $params);

// Backfill epoch summaries
$server->backfillEpochSummary($tokenCredentials, $params);

// Backfill activity details summaries
$server->backfillActivityDetailsSummary($tokenCredentials, $params);

// Backfill sleep summaries
$server->backfillSleepSummary($tokenCredentials, $params);

// Backfill body composition summaries
$server->backfillBodyCompositionSummary($tokenCredentials, $params);

// Backfill stress details summaries
$server->backfillStressDetailsSummary($tokenCredentials, $params);

// Backfill user metrics summaries
$server->backfillUserMetricsSummary($tokenCredentials, $params);

// Backfill pulse ox summaries
$server->backfillPulseOxSummary($tokenCredentials, $params);

// Backfill respiration summaries
$server->backfillRespirationSummary($tokenCredentials, $params);
```

### Get User Metrics (VO2 Max)

The `getUserMetrics` method retrieves user fitness metrics including VO2 max and fitness age. This method requires both `uploadStartTimeInSeconds` and `uploadEndTimeInSeconds` parameters with a maximum 24-hour window.

```php
// Get user metrics for a specific 24-hour period
$params = [
    'uploadStartTimeInSeconds' => 1726876800, // Unix timestamp
    'uploadEndTimeInSeconds' => 1726963200    // Unix timestamp (max 24 hours later)
];

$userMetrics = $server->getUserMetrics($tokenCredentials, $params);

// The response will include VO2 max data and other fitness metrics
// Example response structure:
// {
//   "userMetrics": [
//     {
//       "vo2Max": 45.2,
//       "fitnessAge": 28,
//       "timestamp": "2023-09-21T12:00:00Z"
//     }
//   ]
// }
```

**Important**: The time window for user metrics queries cannot exceed 24 hours.