<?php

use Errly\LaravelErrly\App\Protocol;

beforeEach(function () {
    $this->vectors = json_decode(file_get_contents(__DIR__.'/../fixtures/protocol-v1-vectors.json'), true);
});

test('keys derive as in the vectors', function () {
    $keys = Protocol::keys($this->vectors['key']);

    expect(bin2hex($keys['enc']))->toBe($this->vectors['k_enc_hex'])
        ->and(bin2hex($keys['fp']))->toBe($this->vectors['k_fp_hex']);
});

test('sealing the vector plaintext with the vector nonce gives the vector envelope', function (string $part) {
    $v = $this->vectors;
    $aad = Protocol::aad($v['channel_id'], $v['report_id'], $part);

    $envelope = Protocol::seal($v["{$part}_plaintext"], $aad, Protocol::keys($v['key'])['enc'], hex2bin($v['nonce_hex']));

    expect($aad)->toBe($v["{$part}_aad"])
        ->and($envelope)->toBe($v["{$part}_envelope"])
        ->and(Protocol::envelopeLength(strlen($v["{$part}_plaintext"])))->toBe(strlen($envelope));
})->with(['summary', 'full']);

test('fingerprints match the vectors', function () {
    $fp = Protocol::keys($this->vectors['key'])['fp'];

    expect(Protocol::fingerprint($this->vectors['fingerprint_input'], $fp))->toBe($this->vectors['fingerprint'])
        ->and(Protocol::fingerprint($this->vectors['state_fingerprint_input'], $fp))->toBe($this->vectors['state_fingerprint']);
});

test('the pairing link matches the vector', function () {
    $v = $this->vectors;
    parse_str(parse_url($v['pairing_link'], PHP_URL_QUERY), $query);

    expect(Protocol::pairingLink($v['channel_id'], $v['key'], $query['j'], 'https://errlyservice.tallbottech.com'))
        ->toBe($v['pairing_link']);
});

test('a sealed envelope opens with the same key and associated data only', function () {
    $keys = Protocol::keys(Protocol::newKey());
    $raw = Protocol::base64UrlDecode(Protocol::seal('hello', 'errly/v1|c|r|summary', $keys['enc']));

    $open = fn (string $aad) => sodium_crypto_aead_chacha20poly1305_ietf_decrypt(substr($raw, 13), $aad, substr($raw, 1, 12), $keys['enc']);

    expect($raw[0])->toBe("\x01")
        ->and($open('errly/v1|c|r|summary'))->toBe('hello')
        ->and($open('errly/v1|other|r|summary'))->toBeFalse();
});

test('a key that is not 32 bytes is refused', function () {
    Protocol::keys('c2hvcnQ');
})->throws(InvalidArgumentException::class);
