<?php

namespace atREST\Modules;

use atREST\Module;

/** Symmetric encryption module.
 */

class Encryption
{
    use Module;

    // Constants

    const Tag        = 'Encryption';
    const Algorithm    = 'aes-256-cbc';

    // Constructors & Destructors

    public function __construct()
    {
        if (!self::$ivSize) {
            self::$ivSize = openssl_cipher_iv_length(self::Algorithm);
        }
    }

    // Public Methods

    /** Encrypts data.
     * @param string $plainData the data to be encrypted.
     * @param string $encryptionKey the key to be used when encrypting the data.
     * @return string the base64 encoded encrypted text.
     */
    public function Encrypt(string $plainData, string $encryptionKey)
    {
        $keyHash = substr(hash('sha256', $encryptionKey), 0, 32);
        $encryptionIv = openssl_random_pseudo_bytes(self::$ivSize);
        $encryptedData = openssl_encrypt($plainData, self::Algorithm, $keyHash, OPENSSL_RAW_DATA, $encryptionIv);
        $dataSize = str_pad(strlen($plainData), 6, '0', STR_PAD_LEFT);

        return $encryptedData !== false ? base64_encode($encryptionIv . $dataSize . $encryptedData) : false;
    }

    /** Decyrpts data.
     * @param $encryptedData string the data to be decrypted.
     * @param $encryptionKey string the key to be used when decrypting the data.
     * @return string the decrypted data if the key is correct or an empty string otherwise.
     */
    public function Decrypt(string $encryptedData, string $encryptionKey)
    {
        $encryptedData = base64_decode($encryptedData);
        $encryptionIv = substr($encryptedData, 0, self::$ivSize);
        $dataSize = intval(substr($encryptedData, self::$ivSize, 6));
        $encryptedData = substr($encryptedData, self::$ivSize + 6);
        $keyHash = substr(hash('sha256', $encryptionKey), 0, 32);

        return substr(openssl_decrypt($encryptedData, self::Algorithm, $keyHash, OPENSSL_RAW_DATA, $encryptionIv), 0, $dataSize);
    }

    // Private Members

    private static $ivSize = 0;
}
