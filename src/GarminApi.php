<?php
namespace Stoufa\GarminApi;

use DateTime;
use DateTimeZone;
use League\OAuth1\Client\Server\Server;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use League\OAuth1\Client\Credentials\CredentialsException;
use League\OAuth1\Client\Credentials\CredentialsInterface;
use League\OAuth1\Client\Credentials\TemporaryCredentials;
use League\OAuth1\Client\Credentials\TokenCredentials;
use League\OAuth1\Client\Server\User;

class GarminApi extends Server
{
    /**
     * Version constants
     */
    const VERSION_INTERNATIONAL = 'international';
    const VERSION_CHINESE = 'chinese';

    /**
     * API endpoints for international version
     */
    const INTERNATIONAL_API_URL = "https://connectapi.garmin.com/";
    const INTERNATIONAL_USER_API_URL = "https://healthapi.garmin.com/wellness-api/rest/";
    const INTERNATIONAL_AUTH_URL = "http://connect.garmin.com/oauthConfirm";

    /**
     * API endpoints for Chinese version
     */
    const CHINESE_API_URL = "https://connectapi.garmin.cn/";
    const CHINESE_USER_API_URL = "https://gcs-wellness.garmin.cn/wellness-api/rest/";
    const CHINESE_AUTH_URL = "http://connect.garmin.cn/oauthConfirm";

    /**
     * Current version
     * @var string
     */
    protected $version;

    /**
     * Api connect endpoint
     */
    protected $apiUrl;

    /**
     * Rest api endpoint
     */
    protected $userApiUrl;

    /**
     * Authorization URL
     */
    protected $authUrl;

    /**
     * Constructor to initialize Garmin API with version support
     *
     * @param array $credentials
     * @param string $version
     */
    public function __construct(array $credentials, $version = self::VERSION_INTERNATIONAL)
    {
        parent::__construct($credentials);
        $this->setVersion($version);
    }

    /**
     * Set the Garmin Connect version
     *
     * @param string $version
     * @return void
     * @throws InvalidArgumentException
     */
    public function setVersion($version)
    {
        if (!in_array($version, [self::VERSION_INTERNATIONAL, self::VERSION_CHINESE])) {
            throw new InvalidArgumentException("Invalid version. Must be 'international' or 'chinese'");
        }

        $this->version = $version;

        if ($version === self::VERSION_CHINESE) {
            $this->apiUrl = self::CHINESE_API_URL;
            $this->userApiUrl = self::CHINESE_USER_API_URL;
            $this->authUrl = self::CHINESE_AUTH_URL;
        } else {
            $this->apiUrl = self::INTERNATIONAL_API_URL;
            $this->userApiUrl = self::INTERNATIONAL_USER_API_URL;
            $this->authUrl = self::INTERNATIONAL_AUTH_URL;
        }
    }

    /**
     * Get the current version
     *
     * @return string
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * Get the current API URL
     *
     * @return string
     */
    public function getApiUrl()
    {
        return $this->apiUrl;
    }

    /**
     * Get the current user API URL
     *
     * @return string
     */
    public function getUserApiUrl()
    {
        return $this->userApiUrl;
    }

    /**
     * Get the current auth URL
     *
     * @return string
     */
    public function getAuthUrl()
    {
        return $this->authUrl;
    }

    /**
     * Switch to Chinese version
     *
     * @return void
     */
    public function useChineseVersion()
    {
        $this->setVersion(self::VERSION_CHINESE);
    }

    /**
     * Switch to international version
     *
     * @return void
     */
    public function useInternationalVersion()
    {
        $this->setVersion(self::VERSION_INTERNATIONAL);
    }

    /**
     * Get the URL for retrieving temporary credentials.
     *
     * @return string
     */
    public function urlTemporaryCredentials()
    {
        return $this->apiUrl . 'oauth-service/oauth/request_token';
    }

    /**
     * Get the URL for redirecting the resource owner to authorize the client.
     *
     * @return string
     */
    public function urlAuthorization()
    {
        return $this->authUrl;
    }

    /**
     * Get the URL retrieving token credentials.
     *
     * @return string
     */
    public function urlTokenCredentials()
    {
        return $this->apiUrl . 'oauth-service/oauth/access_token';
    }

    /**
     * Get the authorization URL by passing in the temporary credentials
     * identifier or an object instance.
     *
     * @param TemporaryCredentials|string $temporaryIdentifier
     * @return string
     */
    public function getAuthorizationUrl($temporaryIdentifier, array $options = [])
    {
        // Somebody can pass through an instance of temporary
        // credentials and we'll extract the identifier from there.
        if ($temporaryIdentifier instanceof TemporaryCredentials) {
            $temporaryIdentifier = $temporaryIdentifier->getIdentifier();
        }
        //$parameters = array('oauth_token' => $temporaryIdentifier, 'oauth_callback' => 'http://70.38.37.105:1225');

        $url = $this->urlAuthorization();
        //$queryString = http_build_query($parameters);
        $queryString = "oauth_token=" . $temporaryIdentifier . "&oauth_callback=" . $this->clientCredentials->getCallbackUri();

        return $this->buildUrl($url, $queryString);
    }

    /**
     * Retrieves token credentials by passing in the temporary credentials,
     * the temporary credentials identifier as passed back by the server
     * and finally the verifier code.
     *
     * @param TemporaryCredentials $temporaryCredentials
     * @param string $temporaryIdentifier
     * @param string $verifier
     * @return TokenCredentials
     * @throws CredentialsException If a "bad response" is received by the server
     * @throws GuzzleException
     * @throws InvalidArgumentException
     */
    public function getTokenCredentials(TemporaryCredentials $temporaryCredentials, $temporaryIdentifier, $verifier)
    {
        if ($temporaryIdentifier !== $temporaryCredentials->getIdentifier()) {
            throw new \InvalidArgumentException(
                'Temporary identifier passed back by server does not match that of stored temporary credentials.
                Potential man-in-the-middle.'
            );
        }

        $uri = $this->urlTokenCredentials();
        $bodyParameters = array('oauth_verifier' => $verifier);

        $client = $this->createHttpClient();

        $headers = $this->getHeaders($temporaryCredentials, 'POST', $uri, $bodyParameters);
        try {
            $response = $client->post($uri, [
                'headers' => $headers,
                'form_params' => $bodyParameters
            ]);
        } catch (BadResponseException $e) {
            throw $this->handleTokenCredentialsBadResponse($e);
        }
        
        return $this->createTokenCredentials((string)$response->getBody());
    }

    /**
     * Generate the OAuth protocol header for requests other than temporary
     * credentials, based on the URI, method, given credentials & body query
     * string.
     * 
     * @param string $method
     * @param string $uri
     * @param CredentialsInterface $credentials
     * @param array $bodyParameters
     * @return string
     */
    protected function protocolHeader($method, $uri, CredentialsInterface $credentials, array $bodyParameters = [])
    {
        $parameters = array_merge(
            $this->baseProtocolParameters(),
            $this->additionalProtocolParameters(),
            array(
                'oauth_token' => $credentials->getIdentifier(),

            ),
            $bodyParameters
        );
        $this->signature->setCredentials($credentials);

        $parameters['oauth_signature'] = $this->signature->sign(
            $uri,
            array_merge($parameters, $bodyParameters),
            $method
        );

        return $this->normalizeProtocolParameters($parameters);
    }

    /**
     * Get the base protocol parameters for an OAuth request.
     * Each request builds on these parameters.
     *
     * @see OAuth 1.0 RFC 5849 Section 3.1
     */
    protected function baseProtocolParameters()
    {
        $dateTime = new DateTime('now', new DateTimeZone('UTC'));

        return [
            'oauth_consumer_key' => $this->clientCredentials->getIdentifier(),
            'oauth_nonce' => $this->nonce(),
            'oauth_signature_method' => $this->signature->method(),
            'oauth_timestamp' => $dateTime->format('U'),
            'oauth_version' => '1.0',
        ];
    }

    /**
     * Get activity summary
     *
     * @param TokenCredentials $tokenCredentials
     * @param array $params
     * @return string json response
     * @throws Exception
     */
    public function getActivitySummary(TokenCredentials $tokenCredentials, array $params)
    {
        $client = $this->createHttpClient();
        $query = http_build_query($params);
        $query = 'activities?'.$query;
        $headers = $this->getHeaders($tokenCredentials, 'GET', $this->userApiUrl . $query);

        try {
            $response = $client->get($this->userApiUrl . $query, [
                'headers' => $headers,
            ]);
        } catch (BadResponseException $e) {
            $response = $e->getResponse();
            $body = $response->getBody();
            $statusCode = $response->getStatusCode();

            throw new \Exception(
                "Received error [$body] with status code [$statusCode] when retrieving activity summary."
            );
        }
        return $response->getBody()->getContents();
    }

    /**
     * Get daily summary (/rest/dailies).
     *
     * @param TokenCredentials $tokenCredentials
     * @param array $params
     *
     * @see https://apis.garmin.com/tools/apiDocs#/Summary%20Endpoints/GET_DAILIES
     *
     * @return string json response
     * @throws Exception
     */
    public function getDailySummary(TokenCredentials $tokenCredentials, array $params)
    {
        $client = $this->createHttpClient();
        $query = http_build_query($params);
        $query = 'dailies?'.$query;
        $headers = $this->getHeaders($tokenCredentials, 'GET', $this->userApiUrl . $query);

        try {
            $response = $client->get($this->userApiUrl . $query, [
                'headers' => $headers,
            ]);
        } catch (BadResponseException $e) {
            $response = $e->getResponse();
            $body = $response->getBody();
            $statusCode = $response->getStatusCode();
            throw new \Exception(
                "Received error [$body] with status code [$statusCode] when retrieving daily summary."
            );
        }
        return $response->getBody()->getContents();
    }

    /**
     * get manually activity summary
     *
     * @param TokenCredentials $tokenCredentials
     * @param array $params
     * @return string json response
     * @throws Exception
     */
    public function getManuallyActivitySummary(TokenCredentials $tokenCredentials, array $params)
    {
        $client = $this->createHttpClient();
        $query = http_build_query($params);
        $query = 'manuallyUpdatedActivities?'.$query;
        $headers = $this->getHeaders($tokenCredentials, 'GET', $this->userApiUrl . $query);

        try {
            $response = $client->get($this->userApiUrl . $query, [
                'headers' => $headers,
            ]);
        } catch (BadResponseException $e) {
            $response = $e->getResponse();
            $body = $response->getBody();
            $statusCode = $response->getStatusCode();
            throw new \Exception(
                "Received error [$body] with status code [$statusCode] when retrieving manually activity summary."
            );
        }
        return $response->getBody()->getContents();
    }

    /**
     * get activity details summary
     *
     * @param TokenCredentials $tokenCredentials
     * @param array $params
     * @return string json response
     * @throws Exception
     */
    public function getActivityDetailsSummary(TokenCredentials $tokenCredentials, array $params)
    {
        $client = $this->createHttpClient();
        $query = http_build_query($params);
        $query = 'activityDetails?'.$query;
        $headers = $this->getHeaders($tokenCredentials, 'GET', $this->userApiUrl . $query);

        try {
            $response = $client->get($this->userApiUrl . $query, [
                'headers' => $headers,
            ]);
        } catch (BadResponseException $e) {
            $response = $e->getResponse();
            $body = $response->getBody();
            $statusCode = $response->getStatusCode();
            throw new \Exception(
                "Received error [$body] with status code [$statusCode] when retrieving manually activity summary."
            );
        }
        return $response->getBody()->getContents();
    }
    
    /**
     * send request to back fill summary type
     *
     * @param TokenCredentials $tokenCredentials
     * @param string $uri
     * @param array $params
     * @return void
     * @throws Exception
     */
    public function backfill(TokenCredentials $tokenCredentials, string $uri, array $params)
    {
        $client = $this->createHttpClient();
        $query = http_build_query($params);
        $query = 'backfill/'.$uri.'?'.$query;
        $headers = $this->getHeaders($tokenCredentials, 'GET', $this->userApiUrl . $query);

        try {
            $response = $client->get($this->userApiUrl . $query, [
                'headers' => $headers,
            ]);
        } catch (BadResponseException $e) {
            $response = $e->getResponse();
            $body = $response->getBody();
            $statusCode = $response->getStatusCode();

            throw new \Exception(
                "Received error [$body] with status code [$statusCode] when requesting historic $uri summary."
            );
        }
    }

    /**
     * send request to back fill activity summary
     *
     * @param TokenCredentials $tokenCredentials
     * @param array $params
     * @return void
     * @throws Exception
     */
    public function backfillActivitySummary(TokenCredentials $tokenCredentials, array $params)
    {
        $this->backfill($tokenCredentials, 'activities', $params);
    }

     /**
     * send request to back fill daily activity summary
     *
     * @param TokenCredentials $tokenCredentials
     * @param array $params
     * @return void
     * @throws Exception
     */
    public function backfillDailySummary(TokenCredentials $tokenCredentials, array $params)
    {
        $this->backfill($tokenCredentials, 'dailies', $params);
    }

    /**
     * send request to back fill daily epoch summary
     *
     * @param TokenCredentials $tokenCredentials
     * @param array $params
     * @return void
     * @throws Exception
     */
    public function backfillEpochSummary(TokenCredentials $tokenCredentials, array $params)
    {
        $this->backfill($tokenCredentials, 'epochs', $params);
    }

    /**
     * send request to back fill activity details summary
     *
     * @param TokenCredentials $tokenCredentials
     * @param array $params
     * @return void
     * @throws Exception
     */
    public function backfillActivityDetailsSummary(TokenCredentials $tokenCredentials, array $params)
    {
        $this->backfill($tokenCredentials, 'activityDetails', $params);
    }

    /**
     * send request to back fill sleep summary
     *
     * @param TokenCredentials $tokenCredentials
     * @param array $params
     * @return void
     * @throws Exception
     */
    public function backfillSleepSummary(TokenCredentials $tokenCredentials, array $params)
    {
        $this->backfill($tokenCredentials, 'sleep', $params);
    }

    /**
     * send request to back fill body composition summary
     *
     * @param TokenCredentials $tokenCredentials
     * @param array $params
     * @return void
     * @throws Exception
     */
    public function backfillBodyCompositionSummary(TokenCredentials $tokenCredentials, array $params)
    {
        $this->backfill($tokenCredentials, 'bodyComps', $params);
    }


    /**
     * send request to back fill body composition summary
     *
     * @param TokenCredentials $tokenCredentials
     * @param array $params
     * @return void
     * @throws Exception
     */
    public function backfillStressDetailsSummary(TokenCredentials $tokenCredentials, array $params)
    {
        $this->backfill($tokenCredentials, 'stressDetails', $params);
    }


    /**
     * send request to back fill user metrics summary
     *
     * @param TokenCredentials $tokenCredentials
     * @param array $params
     * @return void
     * @throws Exception
     */
    public function backfillUserMetricsSummary(TokenCredentials $tokenCredentials, array $params)
    {
        $this->backfill($tokenCredentials, 'userMetrics', $params);
    }

    /**
     * send request to back fill pulse ox summary
     *
     * @param TokenCredentials $tokenCredentials
     * @param array $params
     * @return void
     * @throws Exception
     */
    public function backfillPulseOxSummary(TokenCredentials $tokenCredentials, array $params)
    {
        $this->backfill($tokenCredentials, 'pulseOx', $params);
    }

    /**
     * send request to back fill respiration summary
     *
     * @param TokenCredentials $tokenCredentials
     * @param array $params
     * @return void
     * @throws Exception
     */
    public function backfillRespirationSummary(TokenCredentials $tokenCredentials, array $params)
    {
        $this->backfill($tokenCredentials, 'respiration', $params);
    }

    /**
     * delete user access token: deregistration
     *
     * @param TokenCredentials $tokenCredentials
     * @return void
     * @throws Exception
     */
    public function deleteUserAccessToken(TokenCredentials $tokenCredentials)
    {
        $uri = 'user/registration';
        $client = $this->createHttpClient();
        $headers = $this->getHeaders($tokenCredentials, 'DELETE', $this->userApiUrl . $uri);

        try {
            $response = $client->delete($this->userApiUrl . $uri, [
                'headers' => $headers,
            ]);
        } catch (BadResponseException $e) {
            $response = $e->getResponse();
            $body = $response->getBody();
            $statusCode = $response->getStatusCode();

            throw new \Exception(
                "Received error [$body] with status code [$statusCode] when deleting user access token."
            );
        }
    }

    /**
     * returns user details url
     *
     * @return string
     */
    public function urlUserDetails()
    {
        return $this->userApiUrl . 'user/id';
    }

    /**
     * get user details: in garmin there is only user id
     *
     * @param mixed $data
     * @param TokenCredentials $tokenCredentials
     * @return User
     */
    public function userDetails($data, TokenCredentials $tokenCredentials)
    {
        $user = new User();

        $user->uid = $data['userId'];


        $user->extra = (array) $data;

        return $user;
    }

    /**
     * get user id
     *
     * @param mixed $data
     * @param TokenCredentials $tokenCredentials
     *  @return string|int|null
     */
    public function userUid($data, TokenCredentials $tokenCredentials)
    {
        return isset($data['userId']) ? $data['userId'] : null;
    }

    /**
     * Left for compatibilty
     *
     * @param mixed $data
     * @param TokenCredentials $tokenCredentials
     * @return string return empty string
     */
    public function userEmail($data, TokenCredentials $tokenCredentials)
    {
        return '';
    }

    /**
     * Left for compatibilty
     *
     * @param mixed $data
     * @param TokenCredentials $tokenCredentials
     * @return string return empty string
     */
    public function userScreenName($data, TokenCredentials $tokenCredentials)
    {
        return '';
    }
}
