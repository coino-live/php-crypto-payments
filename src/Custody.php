<?php

declare(strict_types=1);

namespace CoinoLive;

/** Custody users: lifetime deposit addresses and balances for the users of your app. */
final class Custody
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * Creates a user, or returns the existing one with the same external_id.
     *
     * @param array{external_id: string, name?: string, email?: string, metadata?: array} $params
     */
    public function create(array $params): array
    {
        return $this->client->request('POST', '/custody/users', $params, null, true)['data'];
    }

    public function retrieve(string $id): array
    {
        return $this->client->request('GET', '/custody/users/' . rawurlencode($id))['data'];
    }

    /**
     * One page of users (50), newest first.
     *
     * @param array{external_id?: string, cursor?: string} $filters
     * @return array{data: list<array>, meta: array{next_cursor: ?string, prev_cursor: ?string}}
     */
    public function list(array $filters = []): array
    {
        return $this->client->request('GET', '/custody/users' . Client::query($filters));
    }

    /**
     * One page of a user's credited deposits (50), newest first.
     *
     * @return array{data: list<array>, next_cursor: ?string}
     */
    public function deposits(string $id, ?string $cursor = null): array
    {
        return $this->client->request('GET', '/custody/users/' . rawurlencode($id) . '/deposits' . Client::query(['cursor' => $cursor]));
    }

    /** Moves a user's balance of one asset to your main balance: all of it, or just $amount. */
    public function transfer(string $id, string $asset, ?string $amount = null): array
    {
        $body = ['asset' => $asset];
        if ($amount !== null) {
            $body['amount'] = $amount;
        }

        return $this->client->request('POST', '/custody/users/' . rawurlencode($id) . '/transfers', $body)['data'];
    }
}
