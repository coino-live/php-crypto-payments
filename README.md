# Coino Live PHP Library: Accept Crypto Payments (API v2)

The official PHP library, version 1.0.0, for the [Coino Live](https://coino.live) REST API v2. Coino Live is a crypto payment gateway. Your customer pays in crypto, the payment is credited to your Coino Live balance, and you withdraw it to your own wallet.

**Requirements:** PHP 8.1+ and cURL, with no other dependencies. **License:** MIT. Full API documentation is in the [Coino Live API guide](https://coino.live/api-guide).

With this library you can:

- create a payment and redirect the customer to its checkout URL
- confirm that a payment is `paid` before you ship the order
- verify webhook signatures
- give each of your users lifetime deposit addresses (custody)
- check balances and send withdrawals to your own wallet
- handle errors with typed exceptions and safe automatic retries

## Supported networks, assets and fees

- **117 assets on 11 networks:** Bitcoin, Litecoin, Ethereum, BNB Smart Chain, opBNB, Polygon, Base, Arbitrum, TRON, TON and Solana. USDT and USDC are available on most of them. Dogecoin and Bitcoin Cash are not accepted.
- **Fees:** 0.25% platform fee per payment. Blockchain network fees are separate.
- **Account:** sign up with email and password, no KYC.

## Installation

Install it with Composer from [Packagist](https://packagist.org/packages/coinolive/coinolive-php):

```bash
composer require coinolive/coinolive-php
```

Or download `coinolive-php-<version>.zip` from [coino.live/api-guide](https://coino.live/api-guide#libraries), unzip it into your project and load it:

```php
require __DIR__ . '/coinolive-php/src/autoload.php';
```

With Composer, add the unzipped folder as a path repository instead:

```bash
composer config repositories.coinolive path ./coinolive-php
composer require "coinolive/coinolive-php:*"
```

## Accept a crypto payment

Your API key is in **Dashboard → Developers**. Create the payment, then redirect the customer to its checkout URL:

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

Ship the order only when a fresh read from the API says `paid`:

```php
$payment = $coino->payments->retrieve($paymentId);
if ($payment['status'] === 'paid') {
    // …
}
```

Payment statuses: `open`, `confirming`, `partially_paid`, `paid`, `expired`, `cancelled`.

## Verify webhook signatures

On your `notify_url`, verify the signature first. Then retrieve the payment from the API and act on that response, not on the webhook body:

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

## Manage payments and balances

List payments, iterate through every page, or cancel a payment while nothing has been received:

```php
$coino->payments->list(['status' => 'paid', 'order_id' => '1001']); // ['data' => […], 'meta' => ['next_cursor' => …]]
foreach ($coino->payments->all(['status' => 'paid']) as $payment) { /* every page */ }
$coino->payments->cancel($paymentId);                  // only while nothing was received
```

Check which currencies you can price in, which assets customers can pay with, and your balance per coin:

```php
$coino->currencies();                                  // prices can be in these
$coino->assets();                                      // customers can pay with these
$coino->balances();                                    // your balance per coin, with usd_value
```

## Custody: lifetime deposit addresses for your users

Create custody users with lifetime deposit addresses, look up their deposits, and transfer a user's balance to yours:

```php
$user = $coino->custody->create(['external_id' => 'user-42']); // lifetime deposit addresses for your users
$coino->custody->retrieve($user['id']);
$coino->custody->list(['external_id' => 'user-42']);
$coino->custody->deposits($user['id']);
$coino->custody->transfer($user['id'], 'usdt-tron');   // move the user's balance to yours
```

## Withdraw to your own wallet

```php
$coino->withdrawals->create(['asset' => 'usdt-tron', 'amount' => '50', 'address' => 'T…', 'reference' => 'payout-77']);
$coino->withdrawals->retrieve($withdrawalId);
```

## Error handling and automatic retries

Every failure throws an exception from `CoinoLive\Exception`:

| Exception | When |
| --- | --- |
| `AuthenticationException` | Wrong or revoked key, or the gateway is suspended (401) |
| `NotFoundException` | Unknown ID, or it belongs to another gateway (404) |
| `ValidationException` | The request was refused; `$e->errors` names the fields (422) |
| `RateLimitException` | Too many requests (429) |
| `ApiException` | Anything else; the parent of the four above, with `$e->status` |
| `ConnectionException` | The API could not be reached |

Network errors, 429 responses and 5xx responses are retried twice with a short pause. Retries happen only for requests that are safe to repeat:

- reads
- `payments->create()`, which always sends an idempotency key (random unless you pass one as the second argument)
- `custody->create()`, because the same `external_id` returns the same user
- `withdrawals->create()` with a `reference`

You can set the timeout, retry count and base URL when you create the client:

```php
$coino = new \CoinoLive\Client('ck_live_…', [
    'timeout' => 30,       // seconds per request
    'max_retries' => 2,
    'base_url' => 'https://coino.live/api/v2',
]);
```

## Running the tests

Run the test suite locally. To also test against a real API, set the API URL and a test key:

```bash
php tests/run.php
COINOLIVE_API_URL=https://… COINOLIVE_API_KEY=ck_test_… php tests/run.php   # also against a real API
```

## License

Released under the MIT License.
