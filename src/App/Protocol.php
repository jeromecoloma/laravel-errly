<?php

namespace Errly\LaravelErrly\App;

use InvalidArgumentException;

/**
 * Errly protocol v1: key derivation, the encrypted envelope, fingerprints and
 * the pairing link. The channel key never leaves the app and the devices.
 */
final class Protocol
{
    public const VERSION = 1;

    private const ENVELOPE_VERSION = "\x01";

    /**
     * @return array{enc: string, fp: string}
     *
     * @throws InvalidArgumentException when the key does not decode to 32 bytes
     */
    public static function keys(string $key): array
    {
        $raw = self::base64UrlDecode($key);

        if (strlen($raw) !== 32) {
            throw new InvalidArgumentException('ERRLY_APP_KEY must be 32 bytes, base64url encoded. Run php artisan errly:pair --rotate to make a new one.');
        }

        return [
            'enc' => hash_hkdf('sha256', $raw, 32, 'errly/v1/enc'),
            'fp' => hash_hkdf('sha256', $raw, 32, 'errly/v1/fingerprint'),
        ];
    }

    public static function newKey(): string
    {
        return self::base64UrlEncode(random_bytes(32));
    }

    /**
     * @param  string|null  $nonce  12 bytes. Random unless given; only tests fix it.
     */
    public static function seal(string $plaintext, string $aad, string $encKey, ?string $nonce = null): string
    {
        $nonce ??= random_bytes(SODIUM_CRYPTO_AEAD_CHACHA20POLY1305_IETF_NPUBBYTES);

        return self::base64UrlEncode(
            self::ENVELOPE_VERSION.$nonce.sodium_crypto_aead_chacha20poly1305_ietf_encrypt($plaintext, $aad, $nonce, $encKey)
        );
    }

    /**
     * @param  'summary'|'full'  $part
     */
    public static function aad(string $channel, string $reportId, string $part): string
    {
        return "errly/v1|{$channel}|{$reportId}|{$part}";
    }

    public static function fingerprint(string $input, string $fpKey): string
    {
        return hash_hmac('sha256', $input, $fpKey);
    }

    public static function pairingLink(string $channel, string $key, string $joinCode, string $url): string
    {
        return 'errly://pair?c='.$channel.'&k='.$key.'&j='.$joinCode.'&u='.rawurlencode($url);
    }

    /**
     * The length of the envelope for a plaintext of the given size.
     */
    public static function envelopeLength(int $plaintextBytes): int
    {
        $bytes = 1 + SODIUM_CRYPTO_AEAD_CHACHA20POLY1305_IETF_NPUBBYTES + $plaintextBytes + SODIUM_CRYPTO_AEAD_CHACHA20POLY1305_IETF_ABYTES;

        return (int) ceil($bytes * 4 / 3);
    }

    public static function base64UrlEncode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $text): string
    {
        $decoded = base64_decode(strtr($text, '-_', '+/'), true);

        return $decoded === false ? '' : $decoded;
    }
}
