<?php

declare(strict_types=1);

namespace CoinoLive;

use CoinoLive\Exception\SignatureVerificationException;

/**
 * Webhook signatures: X-Coino-Signature: t=<unix time>,v1=<hex HMAC-SHA256(secret, "<t>.<raw body>")>.
 *
 *     $event = \CoinoLive\Webhook::verify(file_get_contents('php://input'), $_SERVER['HTTP_X_COINO_SIGNATURE'] ?? '', $secret);
 *     $payment = $coino->payments->retrieve($event['data']['id']); // Read the payment before acting on it.
 */
final class Webhook
{
    /**
     * @return array The decoded event.
     *
     * @throws SignatureVerificationException When the signature is missing, wrong or older than $tolerance seconds.
     */
    public static function verify(string $payload, string $signatureHeader, string $secret, int $tolerance = 300): array
    {
        $parts = [];
        foreach (explode(',', $signatureHeader) as $pair) {
            $kv = explode('=', trim($pair), 2);
            if (count($kv) === 2) {
                $parts[$kv[0]] = $kv[1];
            }
        }
        if (empty($parts['t']) || empty($parts['v1']) || !ctype_digit($parts['t'])) {
            throw new SignatureVerificationException('The X-Coino-Signature header is missing or malformed.');
        }
        if (abs(time() - (int) $parts['t']) > $tolerance) {
            throw new SignatureVerificationException('The signature is too old.');
        }
        if (!hash_equals(hash_hmac('sha256', $parts['t'] . '.' . $payload, $secret), $parts['v1'])) {
            throw new SignatureVerificationException('The signature does not match.');
        }

        $event = json_decode($payload, true);
        if (!is_array($event)) {
            throw new SignatureVerificationException('The payload is not JSON.');
        }

        return $event;
    }
}
