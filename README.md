# Coino Live PHP library

Accept crypto payments with the [Coino Live API](https://coino.live/api-guide). PHP 8.1+, cURL, no other dependencies.

## Install

Download `coinolive-php-<version>.zip` from [coino.live/api-guide](https://coino.live/api-guide#libraries), unzip it
into your project and load it:

```php
require __DIR__ . '/coinolive-php/src/autoload.php';
```

With Composer, add the unzipped folder as a path repository instead:

```bash
composer config repositories.coinolive path ./coinolive-php
composer require "coinolive/coinolive-php:*"
```

## Take a payment

```php
$coino = new \CoinoLive\Client('ck_live_…'); // Dashboard → Developers

$payment = $coino->payments->create([
    'amount' => '25.00',          // a string, at most 2 decimals
    'currency' => 'EUR',          // any code from $coino->currencies(); USD by default
    'order_id' => '1001',
    'success_url' => 'https://shop.example/thanks',
    'cancel_url' => 'https://shop.example/cart',
    'notify_url' => 'https://shop.example/coino-webhook',
]);

header('Location: ' . $payment['checkout_url']);
```

Ship the order when a fresh read says `paid`:

```php
$payment = $coino->payments->retrieve($paymentId);
if ($payment['status'] === 'paid') {
    // …
}
```

Statuses: `open`, `confirming`, `partially_paid`, `paid`, `expired`, `cancelled`.

## Webhooks

```php
use CoinoLive\Exception\SignatureVerificationException;
use CoinoLive\Webhook;

try {
    $event = Webhook::verify(file_get_contents('php://input'), $_SERVER['HTTP_X_COINO_SIGNATURE'] ?? '', $signingSecret);
} catch (SignatureVerificationException $e) {
    http_response_code(401);
    exit;
}

if (str_starts_with($event['event'], 'payment.')) {
    $payment = $coino->payments->retrieve($event['data']['id']); // act on what the API says, not on the webhook body
}
http_response_code(200);
```

## Everything else

```php
$coino->payments->list(['status' => 'paid', 'order_id' => '1001']); // ['data' => […], 'meta' => ['next_cursor' => …]]
foreach ($coino->payments->all(['status' => 'paid']) as $payment) { /* every page */ }
$coino->payments->cancel($paymentId);                  // only while nothing was received

$coino->currencies();                                  // prices can be in these
$coino->assets();                                      // customers can pay with these
$coino->balances();                                    // your balance per coin, with usd_value

$user = $coino->custody->create(['external_id' => 'user-42']); // lifetime deposit addresses for your users
$coino->custody->retrieve($user['id']);
$coino->custody->list(['external_id' => 'user-42']);
$coino->custody->deposits($user['id']);
$coino->custody->transfer($user['id'], 'usdt-tron');   // move the user's balance to yours

$coino->withdrawals->create(['asset' => 'usdt-tron', 'amount' => '50', 'address' => 'T…', 'reference' => 'payout-77']);
$coino->withdrawals->retrieve($withdrawalId);
```

## Errors and retries

Every failure throws an exception from `CoinoLive\Exception`:

| Exception | When |
| --- | --- |
| `AuthenticationException` | Wrong or revoked key, or the gateway is suspended (401) |
| `NotFoundException` | Unknown ID, or it belongs to another gateway (404) |
| `ValidationException` | The request was refused; `$e->errors` names the fields (422) |
| `RateLimitException` | Too many requests (429) |
| `ApiException` | Anything else; the parent of the four above, with `$e->status` |
| `ConnectionException` | The API could not be reached |

Network errors, 429 and 5xx answers are retried twice with a short pause, but only for requests that are safe to repeat:
reads, `payments->create()` (it always sends an idempotency key, random unless you pass one as the second argument),
`custody->create()` (same `external_id`, same user) and `withdrawals->create()` with a `reference`.

```php
$coino = new \CoinoLive\Client('ck_live_…', [
    'timeout' => 30,       // seconds per request
    'max_retries' => 2,
    'base_url' => 'https://coino.live/api/v2',
]);
```

## Tests

```bash
php tests/run.php
COINOLIVE_API_URL=https://… COINOLIVE_API_KEY=ck_test_… php tests/run.php   # also against a real API
```
