<?php

declare(strict_types=1);

namespace CoinoLive;

use CoinoLive\Exception\ApiException;
use CoinoLive\Exception\AuthenticationException;
use CoinoLive\Exception\ConnectionException;
use CoinoLive\Exception\NotFoundException;
use CoinoLive\Exception\RateLimitException;
use CoinoLive\Exception\ValidationException;

/**
 * Coino Live API v2 client.
 *
 *     $coino = new \CoinoLive\Client('ck_live_…');
 *     $payment = $coino->payments->create(['amount' => '25.00', 'currency' => 'EUR', 'order_id' => '1001']);
 *     header('Location: ' . $payment['checkout_url']);
 */
final class Client
{
    public const VERSION = '1.0.0';

    public const DEFAULT_BASE_URL = 'https://coino.live/api/v2';

    public Payments $payments;

    public Custody $custody;

    public Withdrawals $withdrawals;

    private string $apiKey;

    private string $baseUrl;

    private int $timeout;

    private int $maxRetries;

    /**
     * @param array{base_url?: string, timeout?: int, max_retries?: int} $options
     */
    public function __construct(string $apiKey, array $options = [])
    {
        if (!preg_match('/^ck_(live|test)_[A-Za-z0-9]+$/', $apiKey)) {
            throw new \InvalidArgumentException('An API key looks like ck_live_… (create one in your dashboard under Developers).');
        }
        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($options['base_url'] ?? self::DEFAULT_BASE_URL, '/');
        if (!str_starts_with($this->baseUrl, 'https://') && !preg_match('#^http://(localhost|127\.0\.0\.1)(:\d+)?/#', $this->baseUrl . '/')) {
            throw new \InvalidArgumentException('base_url must start with https://.');
        }
        $this->timeout = (int) ($options['timeout'] ?? 30);
        $this->maxRetries = max(0, (int) ($options['max_retries'] ?? 2));

        $this->payments = new Payments($this);
        $this->custody = new Custody($this);
        $this->withdrawals = new Withdrawals($this);
    }

    /** Currencies a payment can be priced in: [['code' => 'EUR', 'usd_per_unit' => '1.09'], …]. */
    public function currencies(): array
    {
        return $this->request('GET', '/currencies')['data'];
    }

    /** Coins customers can pay with right now. */
    public function assets(): array
    {
        return $this->request('GET', '/assets')['data'];
    }

    /** Your account balance per coin, after fees. */
    public function balances(): array
    {
        return $this->request('GET', '/balances')['data'];
    }

    /** @internal "?a=1&b=2" from the non-empty values, or "". */
    public static function query(array $params): string
    {
        $params = array_filter($params, static fn ($v) => $v !== null && $v !== '');

        return $params ? '?' . http_build_query($params) : '';
    }

    /**
     * @internal Use the resource classes.
     *
     * @return array The decoded JSON answer.
     */
    public function request(string $method, string $path, ?array $body = null, ?string $idempotencyKey = null, bool $repeatable = false): array
    {
        // Retrying a POST is only safe when repeating it returns the same object (an idempotency key, a custody
        // external_id, a withdrawal reference).
        $retriable = $method === 'GET' || $idempotencyKey !== null || $repeatable;
        $attempt = 0;

        while (true) {
            try {
                return $this->send($method, $path, $body, $idempotencyKey);
            } catch (ConnectionException | RateLimitException | ApiException $e) {
                $temporary = $e instanceof ConnectionException || $e instanceof RateLimitException || $e->status >= 500;
                if (!$retriable || !$temporary || $attempt >= $this->maxRetries) {
                    throw $e;
                }
                usleep((int) (min(8, 0.5 * (2 ** $attempt)) * 1_000_000));
                $attempt++;
            }
        }
    }

    private function send(string $method, string $path, ?array $body, ?string $idempotencyKey): array
    {
        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Accept: application/json',
            'User-Agent: coinolive-php/' . self::VERSION . ' PHP/' . PHP_VERSION,
        ];
        if ($idempotencyKey !== null) {
            $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
        }

        $ch = curl_init($this->baseUrl . $path);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => $this->timeout,
        ]);
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR));
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        if ($raw === false) {
            throw new ConnectionException('Could not reach Coino Live: ' . $error);
        }
        $json = json_decode((string) $raw, true);

        if ($status >= 200 && $status < 300 && is_array($json)) {
            return $json;
        }
        if (($status >= 300 && $status < 400) || ($status === 404 && !is_array($json))) {
            throw new ApiException(sprintf('There is no Coino Live API at %s (HTTP %d). Check base_url.', $this->baseUrl, $status), $status);
        }

        $message = is_array($json) && isset($json['message']) ? (string) $json['message'] : 'HTTP ' . $status;
        $errors = is_array($json) && isset($json['errors']) && is_array($json['errors']) ? $json['errors'] : [];

        throw match (true) {
            $status === 401 => new AuthenticationException($message, $status),
            $status === 404 => new NotFoundException($message, $status),
            $status === 422 => new ValidationException($message, $status, $errors),
            $status === 429 => new RateLimitException($message, $status),
            default => new ApiException($message, $status, $errors),
        };
    }
}
