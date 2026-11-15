<?php

declare(strict_types=1);

namespace CoinoLive;

/** Payments: create one per order, send the customer to its checkout_url, read it back before shipping. */
final class Payments
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * Creates a payment. Without an idempotency key a random one is used, so a retried request never creates a
     * second payment. Pass your own key (for example "order-1001-1") to make repeating the call from your code safe too.
     *
     * @param array{amount: string, currency?: string, order_id?: string, description?: string, customer_email?: string,
     *              success_url?: string, cancel_url?: string, notify_url?: string, metadata?: array, asset?: string,
     *              expires_in?: int} $params
     */
    public function create(array $params, ?string $idempotencyKey = null): array
    {
        return $this->client->request('POST', '/payments', $params, $idempotencyKey ?? bin2hex(random_bytes(16)))['data'];
    }

    public function retrieve(string $id): array
    {
        return $this->client->request('GET', '/payments/' . rawurlencode($id))['data'];
    }

    /**
     * One page of payments (50), newest first.
     *
     * @param array{status?: string, order_id?: string, cursor?: string} $filters
     * @return array{data: list<array>, meta: array{next_cursor: ?string, prev_cursor: ?string}}
     */
    public function list(array $filters = []): array
    {
        return $this->client->request('GET', '/payments' . Client::query($filters));
    }

    /** Every payment matching the filters, fetching pages as you go. */
    public function all(array $filters = []): \Generator
    {
        do {
            $page = $this->list($filters);
            yield from $page['data'];
            $filters['cursor'] = $page['meta']['next_cursor'] ?? null;
        } while ($filters['cursor'] !== null);
    }

    /** Cancels an open payment that has received nothing. */
    public function cancel(string $id): array
    {
        return $this->client->request('POST', '/payments/' . rawurlencode($id) . '/cancel', null, null, true)['data'];
    }
}
