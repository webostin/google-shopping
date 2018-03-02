<?php

namespace Webostin\Google\Shopping;

use Google\Auth\ApplicationDefaultCredentials;

class ContentSession
{
    public $config;
    public $merchantId;
    public $mcaStatus;
    public $service;
    public $sandboxService;
    public $websiteUrl;

    protected $configDir;

    const CONFIGFILE_NAME = 'merchant-info.json';
    const SERVICE_ACCOUNT_FILE_NAME = 'service-account.json';
    const OAUTH_CLIENT_FILE_NAME = 'client-secrets.json';
    const OAUTH_TOKEN_FILE_NAME = 'stored-token.json';
    const ENDPOINT_ENV_VAR = 'GOOGLE_SHOPPING_SAMPLES_ENDPOINT';

    // Constructor that sets up configuration and authentication for all
    // the samples.
    public function __construct(ConfigInterface $config)
    {
        $options['config_path'] = $config->getConfigPath();

        $this->configDir = join(DIRECTORY_SEPARATOR,
            [$options['config_path'], 'content']);
        $configFile = join(DIRECTORY_SEPARATOR,
            [$this->configDir, self::CONFIGFILE_NAME]);
        if (file_exists($configFile)) {
            $this->config = json_decode(file_get_contents($configFile), true);
            if (is_null($this->config)) {
                throw new \InvalidArgumentException(sprintf('The config file at %s '
                    . 'is not valid JSON. You can use the merchant-info.json file '
                    . 'in the samples root as a template.', $configFile));
            }
        } else {
            printf("No configuration file found at %s\n", $configFile);
            print "Falling back on configuration based on authenticated user.\n";
            $this->config = [];
        }


        $client = new \Google_Client();
        $client->setApplicationName('Content API for Shopping Samples');
        $client->setScopes(\Google_Service_ShoppingContent::CONTENT);
        $this->authenticate($client);

        $this->prepareServices($client);
        $this->retrieveConfig();
    }

    /**
     * Prepares the service and sandboxService fields, taking into
     * consideration any needed endpoint changes.
     */
    private function prepareServices($client)
    {
        $endpoint = getenv(self::ENDPOINT_ENV_VAR);
        if (!empty($endpoint)) {
            $endpointParts = parse_url($endpoint);
            if (!array_key_exists('scheme', $endpointParts)
                || !array_key_exists('host', $endpointParts)) {
                throw new \InvalidArgumentException(
                    'Expected absolute endpoint URL: ' . $endpoint);
            }
            $rootUrl =
                sprintf('%s://%s', $endpointParts['scheme'], $endpointParts['host']);
            if (array_key_exists('port', $endpointParts)) {
                $rootUrl .= ':' . $endpointParts['port'];
            }
            $rootUrl .= '/';
            $basePath = '';
            if (array_key_exists('path', $endpointParts)) {
                $basePath = trim($endpointParts['path'], '/') . '/';
            }

            $this->service =
                $this->getServiceWithEndpoint($client, $rootUrl, $basePath);
            printf("Using non-standard API endpoint: %s%s\n", $rootUrl, $basePath);
        } else {
            $this->service = new \Google_Service_ShoppingContent($client);

            // Fetch the standard rootUrl and basePath to set things up
            // for sandbox creation.
            $class = new \ReflectionClass('Google_Service_Resource');
            $rootProperty = $class->getProperty('rootUrl');
            $rootProperty->setAccessible(true);
            $pathProperty = $class->getProperty('servicePath');
            $pathProperty->setAccessible(true);
            $rootUrl = $rootProperty->getValue($this->service->accounts);
            $basePath = $pathProperty->getValue($this->service->accounts);
        }
        // Attempt to determine a sandbox endpoint from the given endpoint.
        // If we can't, then fall back to using the same endpoint for
        // sandbox methods.
        $pathParts = explode('/', rtrim($basePath, '/'));
        if ($pathParts[count($pathParts) - 1] === 'v2') {
            $pathParts = array_slice($pathParts, 0, -1);
            $pathParts[] = 'v2sandbox';
            $basePath = implode('/', $pathParts) . '/';
        } else {
            print 'Using same endpoint for sandbox methods.';
        }
        $this->sandboxService =
            $this->getServiceWithEndpoint($client, $rootUrl, $basePath);
    }

    /**
     * Creates a new Content API service object from the given client
     * and changes the rootUrl and/or the basePath of the Content API
     * service resource objects within.
     */
    private function getServiceWithEndpoint($client, $rootUrl, $basePath)
    {
        $service = new \Google_Service_ShoppingContent($client);

        // First get the fields that are directly defined in
        // Google_Service_ShoppingContent, as those are the fields that
        // contain the different service resource objects.
        $gsClass = new \ReflectionClass('Google_Service');
        $gsscClass = new \ReflectionClass('Google_Service_ShoppingContent');
        $gsProps = $gsClass->getProperties(\ReflectionProperty::IS_PUBLIC);
        $gsscProps = array_diff(
            $gsscClass->getProperties(\ReflectionProperty::IS_PUBLIC), $gsProps);

        // Prepare the properties we (may) be modifying in these objects.
        $class = new \ReflectionClass('Google_Service_Resource');
        $rootProperty = $class->getProperty('rootUrl');
        $rootProperty->setAccessible(true);
        $pathProperty = $class->getProperty('servicePath');
        $pathProperty->setAccessible(true);

        foreach ($gsscProps as $prop) {
            $resource = $prop->getValue($service);
            $rootProperty->setValue($resource, $rootUrl);
            $pathProperty->setValue($resource, $basePath);
        }

        return $service;
    }

    /**
     * Retrieves information that can be determined via API calls, including
     * configuration fields that were not provided.
     *
     * <p>Retrieves the following fields if missing:
     * <ul>
     * <li>merchantId
     * </ul>
     *
     * <p>Retrieves the following fields, ignoring any existing configuration:
     * <ul>
     * <li>isMCA
     * <li>websiteUrl
     * </ul>
     */
    public function retrieveConfig()
    {
        print "Retrieving account access information for authenticated user.\n";
        $response = $this->service->accounts->authinfo();

        if (is_null($response->getAccountIdentifiers())) {
            throw new \InvalidArgumentException(
                'Authenticated user has no access to any Merchant Center accounts');
        }
        // If there is no configured Merchant Center account ID, use the first one
        // that this user has access to.
        if (array_key_exists('merchantId', $this->config)) {
            $this->merchantId = strval($this->config['merchantId']);
        } else {
            $firstAccount = $response->getAccountIdentifiers()[0];
            if (!is_null($firstAccount->getMerchantId())) {
                $this->merchantId = $firstAccount->getMerchantId();
            } else {
                $this->merchantId = $firstAccount->getAggregatorId();
            }
            printf("Running samples on Merchant Center %d.\n", $this->merchantId);
        }

        // The current account can only be an aggregator if the authenticated
        // account has access to it (is a user) and it's listed in authinfo as
        // an aggregator.
        $this->mcaStatus = false;
        foreach ($response->getAccountIdentifiers() as $accountId) {
            if (!is_null($accountId->getAggregatorId()) &&
                ($accountId->getAggregatorId() === $this->merchantId)) {
                $this->mcaStatus = true;
                break;
            }
            if (!is_null($accountId->getMerchantId()) &&
                ($accountId->getMerchantId() === $this->merchantId)) {
                break;
            }
        }
        printf("Merchant Center %d is%s an MCA.\n",
            $this->merchantId, $this->mcaStatus ? '' : ' not');

        $account = $this->service->accounts->get(
            $this->merchantId, $this->merchantId);
        $this->websiteUrl = $account->getWebsiteUrl();
        if (is_null($this->websiteUrl)) {
            printf("No website listed for Merchant Center %d.\n", $this->merchantId);
        } else {
            printf("Website for Merchant Center %d: %s\n",
                $this->merchantId, $this->websiteUrl);
        }
    }

    /**
     * This function is used as a gate for methods that can only be run
     * on multi-client accounts.
     *
     * @throws InvalidArgumentException if the config does not contain an MCA.
     */
    const MCA_MSG = 'This operation can only be run on multi-client accounts.';

    public function mustBeMCA($msg = self::MCA_MSG)
    {
        if ($this->mcaStatus === false) {
            throw new \InvalidArgumentException($msg);
        }
    }

    /**
     * This function is used as a gate for methods that cannot be run
     * on multi-client accounts.
     *
     * @throws InvalidArgumentException if the config contains an MCA.
     */
    const NON_MCA_MSG = 'This operation cannot be run on multi-client accounts.';

    public function mustNotBeMCA($msg = self::NON_MCA_MSG)
    {
        if ($this->mcaStatus === true) {
            throw new \InvalidArgumentException($msg);
        }
    }

    /**
     * Attempts to find the home directory of the user running the PHP script.
     *
     * @return string The path to the home directory with any trailing directory
     *     separators removed
     * @throws UnexpectedValueException if a home directory could not be found
     */
    public function getHome()
    {
        return null;
    }

    private function getToken(\Google_Client $client)
    {
        $client->setRedirectUri('urn:ietf:wg:oauth:2.0:oob');
        $client->setScopes('https://www.googleapis.com/auth/content');
        $client->setAccessType('offline'); // So that we get a refresh token

        printf("Visit the following URL and log in:\n\n\t%s\n\n",
            $client->createAuthUrl());
        print ('Then type the resulting code here: ');
        $code = trim(fgets(STDIN));
        $client->authenticate($code);

        return $client->getAccessToken();
    }

    protected function cacheToken(\Google_Client $client)
    {
        print (str_repeat('*', 40) . "\n");
        print ("Your token was missing or invalid, fetching a new one\n");
        $token = $this->getToken($client);
        $tokenFile = join(DIRECTORY_SEPARATOR,
            [$this->configDir, self::OAUTH_TOKEN_FILE_NAME]);
        file_put_contents($tokenFile, json_encode($token, JSON_PRETTY_PRINT));
        printf("Token saved to %s\n", $tokenFile);
        print (str_repeat('*', 40) . "\n");
    }

    /**
     * This function looks for authentication in this order:
     * - Google Application Default Credentials
     * - Service account credentials in SERVICE_ACCOUNT_FILE_NAME in the configDir
     * - OAuth2 credentials in OAUTH_CLIENT_FILE_NAME in the configDir
     */
    protected function authenticate(\Google_Client $client)
    {
        try {
            // Try loading the credentials.
            $credentials = ApplicationDefaultCredentials::getCredentials(
                \Google_Service_ShoppingContent::CONTENT);
            // If we got here, the credentials are there, so tell the client.
            $client->useApplicationDefaultCredentials();
            print "Using Google Application Default Credentials.\n";
        } catch (\DomainException $exception) {
            // Safe to ignore this error, since we'll fall back on other creds unless
            // we are not using a configuration directory.
            if (!$this->configDir) {
                throw new \InvalidArgumentException(
                    'Must use Google Application Default Credentials if running '
                    . 'without a configuration directory');
            }
            $this->authenticateFromConfig($client);
        }
    }

    // Handles loading authentication credentials from the config dir.
    protected function authenticateFromConfig(\Google_Client $client)
    {
        $accountFile = join(DIRECTORY_SEPARATOR,
            [$this->configDir, self::SERVICE_ACCOUNT_FILE_NAME]);
        if (file_exists($accountFile)) {
            print 'Loading service account credentials from ' . $accountFile . ".\n";
            $client->setAuthConfig($accountFile);
            $client->setScopes(\Google_Service_ShoppingContent::CONTENT);
            return;
        }
        $oauthFile = join(DIRECTORY_SEPARATOR,
            [$this->configDir, self::OAUTH_CLIENT_FILE_NAME]);
        if (file_exists($oauthFile)) {
            print 'Loading OAuth2 credentials from ' . $oauthFile . ".\n";
            $client->setAuthConfig($oauthFile);
            $tokenFile = join(DIRECTORY_SEPARATOR,
                [$this->configDir, self::OAUTH_TOKEN_FILE_NAME]);
            $token = null;
            if (file_exists($tokenFile)) {
                printf("Loading stored token from '%s'.\n", $tokenFile);
                $token = json_decode(file_get_contents($tokenFile), true);
            }
            if (is_null($token) || !array_key_exists('refresh_token', $token)) {
                $this->cacheToken($client);
            } else {
                try {
                    $client->refreshToken($token['refresh_token']);
                    printf("Successfully loaded token from '%s'.\n", $tokenFile);
                } catch (\Google_Auth_Exception $exception) {
                    $this->cacheToken($client);
                }
            }
            return;
        }
        // All authentication failed.
        $msg = sprintf('Could not find or read credentials from '
            . 'either the Google Application Default credentials, '
            . '%s, or %s.', $accountFile, $oauthFile);
        throw new \DomainException($msg);
    }

    // Retry a function with back off
    public function retry($object, $function, $parameter, $maxAttempts = 5)
    {
        $attempts = 1;

        while ($attempts <= $maxAttempts) {
            try {
                return call_user_func([$object, $function], $parameter);
            } catch (\Google_Service_Exception $exception) {
                $sleepTime = $attempts * $attempts;
                printf("Attempt to call %s failed, retrying in %d second(s).\n",
                    $function, $sleepTime);
                sleep($sleepTime);
                $attempts++;
            }
        }
    }
}