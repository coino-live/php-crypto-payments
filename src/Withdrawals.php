<?php

declare(strict_types=1);

namespace CoinoLive;

/** API withdrawals. Turn them on in the gateway settings first. */
final class Withdrawals
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * Sends coins from your balance (or a custody user's balance) to an address.
     * Give a reference: the same reference returns the same withdrawal, so a retried request never pays twice.
     *
     * @param array{asset: string, amount: string, address: string, custody_user?: string, reference?: string} $params
     */
    public function create(array $params): array
    {
        return $this->client->request('POST', '/withdrawals', $params, null, isset($params['reference']))['data'];
    }

    public function retrieve(string $id): array
    {
        return $this->client->request('GET', '/withdrawals/' . rawurlencode($id))['data'];
    }
}
