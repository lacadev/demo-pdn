<?php

namespace App\Helpers;

/**
 * Utility class for encrypting and decrypting sensitive data.
 */
class Crypto
{
    private const CIPHER_ALGO = 'aes-256-cbc';

    private static function getKey(): string
    {
        if (defined('AUTH_KEY') && AUTH_KEY !== 'put your unique phrase here') {
            return substr(hash('sha256', AUTH_KEY), 0, 32);
        }
        return substr(hash('sha256', 'laca_fallback_secret_key_if_auth_key_missing'), 0, 32);
    }

    public static function encrypt(string $plainText): string
    {
        if (empty($plainText)) {
            return '';
        }
        $ivLength = openssl_cipher_iv_length(self::CIPHER_ALGO);
        $iv = openssl_random_pseudo_bytes($ivLength);
        $encrypted = openssl_encrypt($plainText, self::CIPHER_ALGO, self::getKey(), OPENSSL_RAW_DATA, $iv);
        if ($encrypted === false) {
            return '';
        }
        return base64_encode($iv . $encrypted);
    }

    public static function decrypt(string $encryptedText): string
    {
        if (empty($encryptedText)) {
            return '';
        }
        $decoded = base64_decode($encryptedText, true);
        if ($decoded === false) {
            return $encryptedText;
        }
        $ivLength = openssl_cipher_iv_length(self::CIPHER_ALGO);
        if (strlen($decoded) <= $ivLength) {
            return $encryptedText;
        }
        $iv        = substr($decoded, 0, $ivLength);
        $encrypted = substr($decoded, $ivLength);
        $decrypted = openssl_decrypt($encrypted, self::CIPHER_ALGO, self::getKey(), OPENSSL_RAW_DATA, $iv);
        return $decrypted !== false ? $decrypted : $encryptedText;
    }

    public static function isEncrypted(string $text): bool
    {
        if (empty($text) || strlen($text) < 32) {
            return false;
        }
        if (!preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $text)) {
            return false;
        }
        $decoded = base64_decode($text, true);
        return $decoded !== false && strlen($decoded) > openssl_cipher_iv_length(self::CIPHER_ALGO);
    }
}
