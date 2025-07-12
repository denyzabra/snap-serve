<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Service for generating secure tokens for various purposes
 * Used for email verification, password reset, and other security operations
 */
class TokenGeneratorService
{
    public function __construct(
        #[Autowire('%kernel.secret%')] 
        private string $secret
    ) {
    }

    /**
     * Generate a cryptographically secure random token
     * Uses random_bytes() which is cryptographically secure
     * 
     * @param int $length Length of the token in bytes (will be doubled in hex)
     * @return string Hexadecimal representation of the token
     */
    public function generateSecureToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }

    /**
     * Generate a token with embedded expiration time and signature
     * Useful for time-limited operations like password reset
     * 
     * @param string $identifier Unique identifier for the token
     * @param int $expirationTime Unix timestamp when token expires
     * @return string Signed token with embedded expiration
     */
    public function generateTimeLimitedToken(string $identifier, int $expirationTime): string
    {
        $data = [
            'identifier' => $identifier,
            'expires' => $expirationTime,
            'random' => bin2hex(random_bytes(16)),
            'issued_at' => time()
        ];
        
        $payload = base64_encode(json_encode($data));
        $signature = hash_hmac('sha256', $payload, $this->secret);
        
        return $payload . '.' . $signature;
    }

    /**
     * Validate and decode a time-limited token
     * Verifies signature and expiration time
     * 
     * @param string $token The token to validate
     * @return array|null Token data if valid, null if invalid
     */
    public function validateTimeLimitedToken(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return null;
        }

        [$payload, $signature] = $parts;
        
        // Verify signature to prevent tampering
        $expectedSignature = hash_hmac('sha256', $payload, $this->secret);
        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        // Decode and validate payload
        $data = json_decode(base64_decode($payload), true);
        if (!$data || !isset($data['expires'])) {
            return null;
        }

        // Check if token has expired
        if ($data['expires'] < time()) {
            return null;
        }

        return $data;
    }

    /**
     * Generate a numeric code for SMS or simple verification
     * 
     * @param int $length Length of the numeric code
     * @return string Numeric verification code
     */
    public function generateNumericCode(int $length = 6): string
    {
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= random_int(0, 9);
        }
        return $code;
    }

    /**
     * Generate a URL-safe token for public use
     * Uses base64url encoding to ensure URL safety
     * 
     * @param int $length Length in bytes
     * @return string URL-safe token
     */
    public function generateUrlSafeToken(int $length = 32): string
    {
        $token = base64_encode(random_bytes($length));
        // Make it URL-safe by replacing characters
        return strtr($token, '+/', '-_');
    }

    /**
     * Generate a token with specific prefix
     * Useful for identifying token types
     * 
     * @param string $prefix Token prefix (e.g., 'verify_', 'reset_')
     * @param int $length Length of random part
     * @return string Prefixed token
     */
    public function generatePrefixedToken(string $prefix, int $length = 32): string
    {
        return $prefix . $this->generateSecureToken($length);
    }

    /**
     * Validate token format and basic structure
     * 
     * @param string $token Token to validate
     * @param int $expectedLength Expected token length
     * @return bool True if format is valid
     */
    public function validateTokenFormat(string $token, int $expectedLength = 64): bool
    {
        // Check length (64 hex characters = 32 bytes)
        if (strlen($token) !== $expectedLength) {
            return false;
        }

        // Check if it's valid hexadecimal
        if (!ctype_xdigit($token)) {
            return false;
        }

        return true;
    }

    /**
     * Generate a token with checksum for additional validation
     * 
     * @param string $data Data to include in token
     * @return string Token with embedded checksum
     */
    public function generateTokenWithChecksum(string $data): string
    {
        $randomPart = $this->generateSecureToken(16);
        $payload = base64_encode(json_encode([
            'data' => $data,
            'random' => $randomPart,
            'timestamp' => time()
        ]));
        
        $checksum = hash_hmac('sha256', $payload, $this->secret);
        
        return $payload . '.' . substr($checksum, 0, 8);
    }

    /**
     * Validate token with checksum
     * 
     * @param string $token Token to validate
     * @return array|null Decoded data if valid, null if invalid
     */
    public function validateTokenWithChecksum(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return null;
        }

        [$payload, $providedChecksum] = $parts;
        
        // Verify checksum
        $expectedChecksum = substr(hash_hmac('sha256', $payload, $this->secret), 0, 8);
        if (!hash_equals($expectedChecksum, $providedChecksum)) {
            return null;
        }

        // Decode payload
        $data = json_decode(base64_decode($payload), true);
        if (!$data) {
            return null;
        }

        return $data;
    }
}
