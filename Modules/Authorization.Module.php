<?php

namespace atREST\Modules;

use atREST\Core;
use atREST\Module;

/** A token generation and validation module.
 */

class Authorization
{
    use Module;

    // Constants

    const Tag    = 'Authorization';

    const SaltParameterName                = 'Authorization.Salt';
    const KeyParameterName                = 'Authorization.Key';
    const LifetimeParameterName            = 'Authorization.Lifetime';
    const RefreshWindowParameterName    = 'Authorization.RefreshWindow';

    const DefaultLifetime        = 60;
    const DefaultRefreshWindow    = 10;

    // Constructors & Destructors

    /** Creates a new authorization object.
     *
     * Initializes and configures the authorization module if not initialized yet.
     *
     * The following configuration parameters must be set for the authorization module to work properly:
     *
     * Authorization.Salt: a salt to be used when encrypting tokens.
     * Authorization.Key: a key to be used in token encryption.
     * Authorization.Lifetime: for how many seconds (by default) the token should be considered valid before it expires.
     */

    public function __construct()
    {
        if (self::$tokenSalt != '') {
            return;
        }

        self::$tokenSalt = Core::Configuration(self::SaltParameterName);
        self::$tokenSaltLength = strlen(self::$tokenSalt);
        self::$encryptionKey = Core::Configuration(self::KeyParameterName);
        self::$tokenLifetime = Core::Configuration(self::LifetimeParameterName, self::DefaultLifetime);
        self::$refreshWindow = Core::Configuration(self::RefreshWindowParameterName, self::DefaultRefreshWindow);
        self::$encryptionModule = Core::Module('Encryption');
    }

    // Public Methods

    /** Authorizes a new ID on the global authorization space with the default token lifetime.
     *
     * If a custom/private token is needed, please use CreateToken().
     *
     * @param string $authorizationID the unique ID to be associated with the authorization token.
     * @param mixed $authorizationPayload (optional) extra data to be included in the token.
     * @return string the generated token string for the authorization (if needed, it can also be read from AuthorizedTokenString()).
     * @see AuthorizedID(), AuthorizedToken(), AuthorizedTokenData(), AuthorizedPayload(), CreateToken(), Deauthorize()
     */

    public function Authorize(string $authorizationID, $authorizationPayload = null)
    {
        $tokenData = null;

        if (self::$authorizedToken = self::CreateToken($authorizationID, self::$tokenLifetime, $authorizationPayload, $tokenData)) {
            self::$authorizedTokenData = $tokenData;
            return self::$authorizedToken;
        }

        return false;
    }

    /** Removes the currently authorized token from the global authorization space.
     */

    public function Deauthorize()
    {
        self::$authorizedToken = '';
        self::$authorizedTokenData = null;
    }

    /** Validates a token and, if valid, puts it into the global authorization space.
     *
     * If a custom/private token must be validated, please use ValidateToken().
     *
     * @param string $authorizationToken the token string to be validated.
     * @return mixed the authorized token ID or false if the token is not valid.
     * @see AuthorizedID(), AuthorizedToken(), AuthorizedTokenData(), AuthorizedPayload()
     */

    public function Validate(?string $authorizationToken)
    {
        $autorizedPlayload = null;

        if ($tokenData = self::ValidateToken($authorizationToken, $autorizedPlayload)) {
            self::$authorizedToken = $authorizationToken;
            self::$authorizedTokenData = $tokenData;
            return $tokenData['ID'];
        }

        return false;
    }

    /** Generates a new token for the currently authorized token in the global authorization space.
     * To be succefully refresh a token must be inside its refresh window.
     *
     * @return mixed the newly created token or false in case of errors.
     */

    public function Refresh()
    {
        if (!self::$authorizedTokenData) {
            return false;
        }

        $requestTime = time();

        if ((self::$authorizedTokenData['Expires'] <= $requestTime) ||
            (self::$authorizedTokenData['Expires'] - $requestTime > self::$refreshWindow)
        ) {
            return false;
        }

        return self::$authorizedToken = self::RefreshToken(self::$authorizedTokenData);
    }

    /** Returns the current globally authorized ID.
     * @return string the current globally authorized ID or an empty string if no ID is authorized.
     * @see AuthorizedToken(), AuthorizedTokenData(), AuthorizedPayload()
     */

    public function AuthorizedID()
    {
        return isset(self::$authorizedTokenData['ID']) ? self::$authorizedTokenData['ID'] : '';
    }

    /** Returns the current globally authorized token.
     * @return string the current globally authorized token or an empty string if no token is authorized.
     * @see AuthorizedID(), AuthorizedTokenData(), AuthorizedPayload()
     */

    public function AuthorizedToken()
    {
        return self::$authorizedToken;
    }

    /** Returns the current globally authorized token data.
     * @return array the current globally authorized token data or null if no token is authorized.
     * @see AuthorizedID(), AuthorizedPayload(), AuthorizedToken()
     */

    public function AuthorizedTokenData()
    {
        return self::$authorizedTokenData;
    }

    /** Returns the current globally authorized token playload.
     * @return mixed the current globally authorized token playload or null if there is not playload or there is no token is authorized.
     * @see AuthorizedID(), AuthorizedToken()
     */

    public function AuthorizedPayload()
    {
        return isset(self::$authorizedTokenData['Payload']) ? self::$authorizedTokenData['Payload'] : null;
    }

    // FIXME: add documentation comments.

    public function TokenLifetime()
    {
        return self::$tokenLifetime;
    }

    public function RefreshWindow()
    {
        return self::$refreshWindow;
    }

    /** Creates a private/custom token.
     *
     * To create a global authorization token, please use Authorize().
     *
     * @param string $authorizationID string the authorization ID.
     * @param int $tokenLifetime the token lifetime in seconds.
     * @param mixed $tokenPayload extra data to be included in the token.
     * @param array $tokenData (output) the token data used to generate the token string.
     * @return string the created token or null in case of errors.
     * @see Authorize(), ValidateToken()
     */

    public function CreateToken(string $authorizationID, int $tokenLifetime, $tokenPayload, &$tokenData)
    {
        if (!self::$encryptionModule) {
            return null;
        }

        if (!$tokenLifetime) {
            $tokenLifetime = self::$tokenLifetime;
        }

        $tokenData = array(
            'ID' => $authorizationID,
            'Lifetime' => $tokenLifetime,
            'Expires' => time() + $tokenLifetime,
        );

        if ($tokenPayload) {
            $tokenData['Playload'] = $tokenPayload;
        }

        return self::$encryptionModule->Encrypt(self::$tokenSalt . json_encode($tokenData, JSON_FORCE_OBJECT), self::$encryptionKey);
    }

    /** Validates a private/custom token.
     *
     * To validate a globally authorized token, please use Validate().
     *
     * @param string $tokenString the token data to be validated.
     * @param mixed $decodedPayload the decoded extra data found in the token.
     * @return array the validated decoded token or false if the token is not valid.
     * @see Validate()
     */

    public function ValidateToken(?string $tokenString, &$decodedPayload)
    {
        if (!self::$encryptionModule || empty($tokenString)) {
            return false;
        }

        // For the token to be valid, the authorization salt must be valid and the token must not have expired.

        $tokenData = self::$encryptionModule->Decrypt($tokenString, self::$encryptionKey);

        if (substr($tokenData, 0, self::$tokenSaltLength) != self::$tokenSalt) {
            return false;
        }

        if (!$tokenData = json_decode(substr($tokenData, self::$tokenSaltLength), true)) {
            return false;
        }

        if (!isset($tokenData['ID']) || !isset($tokenData['Lifetime']) || !isset($tokenData['Expires'])) {
            return false;
        }

        if (intval($tokenData['Expires']) <= time()) {
            return false;
        }

        return $tokenData;
    }

    // FIXME: add documentation comment.

    public function RefreshToken(array &$tokenData)
    {
        if (!isset($tokenData['ID']) || !isset($tokenData['Lifetime']) || !isset($tokenData['Expires'])) {
            return false;
        }

        return self::CreateToken($tokenData['ID'], $tokenData['Lifetime'], isset($tokenData['Payload']) ? $tokenData['Payload'] : null, $tokenData);
    }

    // Private Members

    private static $tokenSalt = '';
    private static $encryptionKey = '';
    private static $tokenSaltLength = 0;
    private static $tokenLifetime = 0;
    private static $encryptionModule = null;
    private static $refreshWindow = 0;
    private static $authorizedToken = '';
    private static $authorizedTokenData = null;
}
