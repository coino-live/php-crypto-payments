<?php

declare(strict_types=1);

namespace CoinoLive\Exception;

/** Too many requests (429). Retried automatically, then thrown. */
final class RateLimitException extends ApiException
{
}
