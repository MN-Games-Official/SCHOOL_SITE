<?php
/**
 * ============================================================================
 * Token - Token Generation & Verification
 * StudyFlow - Student Self-Teaching App
 *
 * Provides cryptographically secure token generation, hashing, verification,
 * API key creation, time-limited tokens, and simple JWT-like encoding/decoding.
 *
 * All methods are static for convenience.
 * ============================================================================
 */

class Token
{
    /** @var string Hash algorithm for token hashing */
    private static string $hashAlgo = 'sha256';

    /** @var string HMAC algorithm for JWT-like tokens */
    private static string $hmacAlgo = 'sha256';

    /** @var string API key prefix */
    private static string $apiKeyPrefix = 'sf_';

    // -------------------------------------------------------------------------
    // Basic Token Operations
    // -------------------------------------------------------------------------

    /**
     * Generate a cryptographically secure random token.
     *
     * @param int $length Desired length of the hex string (actual entropy
     *                    is length / 2 bytes). Minimum 16.
     * @return string Hex-encoded token
     *
     * @throws InvalidArgumentException If length < 16
     * @throws \Random\RandomException  If system randomness source fails
     */
    public static function generate(int $length = 64): string
    {
        if ($length < 16) {
            throw new InvalidArgumentException(
                'Token length must be at least 16 characters.'
            );
        }

        // random_bytes returns raw bytes; bin2hex doubles the length
        $bytes = (int) ceil($length / 2);
        $token = bin2hex(random_bytes($bytes));

        // Trim to exact requested length
        return substr($token, 0, $length);
    }

    /**
     * Hash a token for secure storage.
     *
     * Tokens should be hashed before persisting so that a data leak
     * does not expose usable tokens.
     *
     * @param string $token Raw token
     * @return string Hashed token (hex)
     */
    public static function hash(string $token): string
    {
        return hash(static::$hashAlgo, $token);
    }

    /**
     * Verify a raw token against its stored hash.
     *
     * Uses timing-safe comparison to prevent timing attacks.
     *
     * @param string $token Raw token presented by the user
     * @param string $hash  Stored hash to compare against
     * @return bool
     */
    public static function verify(string $token, string $hash): bool
    {
        $computed = hash(static::$hashAlgo, $token);
        return hash_equals($hash, $computed);
    }

    // -------------------------------------------------------------------------
    // API Keys
    // -------------------------------------------------------------------------

    /**
     * Generate an API key with a recognisable prefix.
     *
     * Format:  sf_<32 hex chars>
     * Example: sf_a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6
     *
     * @return string
     */
    public static function generateApiKey(): string
    {
        $randomPart = bin2hex(random_bytes(16));
        return static::$apiKeyPrefix . $randomPart;
    }

    /**
     * Check whether a string looks like a valid API key format.
     *
     * @param string $key
     * @return bool
     */
    public static function isValidApiKeyFormat(string $key): bool
    {
        $prefix = preg_quote(static::$apiKeyPrefix, '/');
        return preg_match('/^' . $prefix . '[a-f0-9]{32}$/', $key) === 1;
    }

    // -------------------------------------------------------------------------
    // Time-Limited Tokens
    // -------------------------------------------------------------------------

    /**
     * Create a token that encodes arbitrary data and an expiration time.
     *
     * The token is a base64url-encoded JSON payload appended with an HMAC
     * signature. A per-application secret is derived from a combination of
     * the file path and a random salt generated once and stored in the
     * data directory.
     *
     * @param array $data Payload data
     * @param int   $ttl  Time-to-live in seconds
     *
     * @return string Opaque token string
     */
    public static function createTimedToken(array $data, int $ttl): string
    {
        $payload = [
            'data' => $data,
            'exp'  => time() + $ttl,
            'iat'  => time(),
            'jti'  => bin2hex(random_bytes(8)),
        ];

        $json    = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $encoded = static::base64UrlEncode($json);
        $sig     = static::sign($encoded, static::getInternalSecret());

        return $encoded . '.' . $sig;
    }

    /**
     * Verify and decode a timed token.
     *
     * @param string $token Token string from createTimedToken()
     *
     * @return array|null Payload data on success, null on failure or expiration
     */
    public static function verifyTimedToken(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return null;
        }

        [$encoded, $sig] = $parts;

        // Verify signature
        $expected = static::sign($encoded, static::getInternalSecret());
        if (!hash_equals($expected, $sig)) {
            return null;
        }

        $json = static::base64UrlDecode($encoded);
        if ($json === false) {
            return null;
        }

        $payload = json_decode($json, true);
        if (!is_array($payload)) {
            return null;
        }

        // Check expiration
        if (!isset($payload['exp']) || $payload['exp'] < time()) {
            return null;
        }

        return $payload['data'] ?? null;
    }

    // -------------------------------------------------------------------------
    // JWT-Like Encoding
    // -------------------------------------------------------------------------

    /**
     * Encode a payload into a simple JWT-like token (header.payload.signature).
     *
     * This is NOT a full JWT implementation. It provides a lightweight
     * signed-token mechanism suitable for internal use.
     *
     * @param array  $payload Arbitrary data
     * @param string $secret  Signing secret
     *
     * @return string Token string
     */
    public static function encodeJwtLike(array $payload, string $secret): string
    {
        $header = static::base64UrlEncode(json_encode([
            'typ' => 'SFT', // StudyFlow Token
            'alg' => 'HS256',
        ]));

        $payload['iat'] = $payload['iat'] ?? time();

        $body = static::base64UrlEncode(json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ));

        $signature = static::sign("{$header}.{$body}", $secret);

        return "{$header}.{$body}.{$signature}";
    }

    /**
     * Decode and verify a JWT-like token.
     *
     * @param string $token  Token string from encodeJwtLike()
     * @param string $secret Signing secret used during encoding
     *
     * @return array|null Decoded payload or null on failure
     */
    public static function decodeJwtLike(string $token, string $secret): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$header, $body, $sig] = $parts;

        // Verify signature
        $expected = static::sign("{$header}.{$body}", $secret);
        if (!hash_equals($expected, $sig)) {
            return null;
        }

        // Decode header
        $headerData = json_decode(static::base64UrlDecode($header), true);
        if (!is_array($headerData)) {
            return null;
        }
        if (($headerData['alg'] ?? '') !== 'HS256') {
            return null;
        }

        // Decode payload
        $payload = json_decode(static::base64UrlDecode($body), true);
        if (!is_array($payload)) {
            return null;
        }

        // Check expiration if present
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    // -------------------------------------------------------------------------
    // CSRF Convenience
    // -------------------------------------------------------------------------

    /**
     * Generate a CSRF token and store it in the session.
     *
     * @return string
     */
    public static function csrf(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return '';
        }

        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = static::generate(64);
        }

        return $_SESSION['_csrf_token'];
    }

    /**
     * Verify a submitted CSRF token.
     *
     * @param string $token Token from the form submission
     * @return bool
     */
    public static function verifyCsrf(string $token): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }

        $stored = $_SESSION['_csrf_token'] ?? '';
        if ($stored === '' || $token === '') {
            return false;
        }

        return hash_equals($stored, $token);
    }

    // -------------------------------------------------------------------------
    // Internal Helpers
    // -------------------------------------------------------------------------

    /**
     * URL-safe Base64 encode.
     *
     * @param string $data
     * @return string
     */
    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * URL-safe Base64 decode.
     *
     * @param string $data
     * @return string|false
     */
    private static function base64UrlDecode(string $data): string|false
    {
        $remainder = strlen($data) % 4;
        $padded = $remainder ? str_pad($data, strlen($data) + (4 - $remainder), '=') : $data;
        return base64_decode(strtr($padded, '-_', '+/'), true);
    }

    /**
     * Compute an HMAC signature.
     *
     * @param string $data   Data to sign
     * @param string $secret Secret key
     * @return string Hex-encoded HMAC
     */
    private static function sign(string $data, string $secret): string
    {
        return hash_hmac(static::$hmacAlgo, $data, $secret);
    }

    /**
     * Retrieve (or create) an internal secret used for timed tokens.
     *
     * The secret is stored in a file so it persists across requests
     * but is unique per installation.
     *
     * @return string
     */
    private static function getInternalSecret(): string
    {
        $secretFile = dirname(__DIR__) . '/data/.app_secret';

        if (file_exists($secretFile)) {
            $secret = file_get_contents($secretFile);
            if ($secret !== false && strlen($secret) >= 32) {
                return $secret;
            }
        }

        // Generate a new secret
        $secret = bin2hex(random_bytes(32));

        $dir = dirname($secretFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($secretFile, $secret, LOCK_EX);
        chmod($secretFile, 0600);

        return $secret;
    }
}
